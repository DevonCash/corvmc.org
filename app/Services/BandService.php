<?php

namespace App\Services;

use App\Models\Band;
use App\Models\User;
use App\Notifications\BandClaimedNotification;
use App\Notifications\BandInvitationAcceptedNotification;
use App\Notifications\BandInvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BandService
{
    /**
     * Invite a user to join a band.
     */
    public function inviteMember(
        Band $band,
        User $user,
        string $role = 'member',
        ?string $position = null,
        ?string $displayName = null
    ): bool {
        if ($this->hasMember($band, $user) || $this->hasInvitedUser($band, $user)) {
            return false;
        }

        DB::transaction(function () use ($band, $user, $role, $position, $displayName) {
            // Add invitation to pivot table
            $band->members()->attach($user->id, [
                'role' => $role,
                'position' => $position,
                'name' => $displayName,
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            // Send notification
            $user->notify(new BandInvitationNotification($band, $role, $position));
        });

        return true;
    }

    /**
     * Accept an invitation to join a band.
     */
    public function acceptInvitation(Band $band, User $user): bool
    {
        if (! $this->hasInvitedUser($band, $user)) {
            return false;
        }

        DB::transaction(function () use ($band, $user) {
            // Update status to active
            $band->members()->updateExistingPivot($user->id, [
                'status' => 'active',
            ]);

            // Notify band owner and admins about the new member
            $this->notifyBandLeadership($band, $user, 'accepted');
        });

        return true;
    }

    /**
     * Decline an invitation to join a band.
     */
    public function declineInvitation(Band $band, User $user): bool
    {
        if (! $this->hasInvitedUser($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);

        return true;
    }

    /**
     * Add a member directly to a band (without invitation).
     */
    public function addMember(
        Band $band,
        User $user,
        string $role = 'member',
        ?string $position = null,
        ?string $displayName = null
    ): bool {
        if ($this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->attach($user->id, [
            'role' => $role,
            'position' => $position,
            'name' => $displayName,
            'status' => 'active',
        ]);

        return true;
    }

    /**
     * Remove a member from a band.
     */
    public function removeMember(Band $band, User $user): bool
    {
        // Cannot remove the owner
        if ($band->owner_id === $user->id) {
            return false;
        }

        return $band->members()->detach($user->id) > 0;
    }

    /**
     * Update a member's role in the band.
     */
    public function updateMemberRole(Band $band, User $user, string $role): bool
    {
        // Cannot change owner's role
        if ($band->owner_id === $user->id) {
            return false;
        }

        if (! $this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['role' => $role]);

        return true;
    }

    /**
     * Update a member's position in the band.
     */
    public function updateMemberPosition(Band $band, User $user, ?string $position): bool
    {
        if (! $this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['position' => $position]);

        return true;
    }

    /**
     * Update a member's display name in the band.
     */
    public function updateMemberDisplayName(Band $band, User $user, ?string $displayName): bool
    {
        if (! $this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['name' => $displayName]);

        return true;
    }

    /**
     * Resend an invitation to a user.
     */
    public function resendInvitation(Band $band, User $user): bool
    {
        if (! $this->hasInvitedUser($band, $user)) {
            return false;
        }

        // Update the invited_at timestamp
        $band->members()->updateExistingPivot($user->id, [
            'invited_at' => now(),
        ]);

        // Get the current invitation details
        $member = $band->members()->wherePivot('user_id', $user->id)->first();

        // Resend notification
        $user->notify(new BandInvitationNotification(
            $band,
            $member->pivot->role,
            $member->pivot->position
        ));

        return true;
    }

    /**
     * Re-invite a user who previously declined.
     */
    public function reInviteDeclinedUser(Band $band, User $user): bool
    {
        if (! $this->hasDeclinedUser($band, $user)) {
            return false;
        }

        // Get the current invitation details
        $member = $band->members()->wherePivot('user_id', $user->id)->first();

        // Update status back to invited
        $band->members()->updateExistingPivot($user->id, [
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        // Send notification
        $user->notify(new BandInvitationNotification(
            $band,
            $member->pivot->role,
            $member->pivot->position
        ));

        return true;
    }

    /**
     * Transfer ownership of a band to another member.
     */
    public function transferOwnership(Band $band, User $newOwner): bool
    {
        if (! $this->hasMember($band, $newOwner)) {
            return false;
        }

        DB::transaction(function () use ($band, $newOwner) {
            $oldOwner = $band->owner;

            // Update band ownership
            $band->update(['owner_id' => $newOwner->id]);

            // Add old owner as admin if they weren't already a member
            if (! $this->hasMember($band, $oldOwner)) {
                $this->addMember($band, $oldOwner, 'admin');
            } else {
                // Update their role to admin
                $this->updateMemberRole($band, $oldOwner, 'admin');
            }
        });

        return true;
    }

    /**
     * Get all pending invitations for a user.
     */
    public function getPendingInvitationsForUser(User $user): Collection
    {
        return Band::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', 'invited');
        })->with(['members' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', 'invited')
                ->withPivot('role', 'position', 'invited_at');
        }])->get();
    }

    /**
     * Get all users available for invitation (CMC members not in the band).
     */
    public function getAvailableUsersForInvitation(Band $band, string $search = ''): Collection
    {
        return User::where('name', 'like', "%{$search}%")
            ->whereDoesntHave('Bands', fn ($query) => $query->where('band_profile_id', $band->id)
            )
            ->limit(50)
            ->get();
    }

    /**
     * Check if a user is a member of the band.
     */
    public function hasMember(Band $band, User $user): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Check if a user has been invited to the band.
     */
    public function hasInvitedUser(Band $band, User $user): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'invited')
            ->exists();
    }

    /**
     * Check if a user has declined an invitation to the band.
     */
    public function hasDeclinedUser(Band $band, User $user): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'declined')
            ->exists();
    }

    /**
     * Get a user's role in the band.
     */
    public function getUserRole(Band $band, User $user): ?string
    {
        if ($band->owner_id === $user->id) {
            return 'owner';
        }

        $membership = $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->first();

        return $membership?->pivot->role;
    }

    /**
     * Check if a user is an admin of the band.
     */
    public function hasAdmin(Band $band, User $user): bool
    {
        return $this->getUserRole($band, $user) === 'admin';
    }

    /**
     * Check if a user is the owner of the band.
     */
    public function isOwner(Band $band, User $user): bool
    {
        return $band->owner_id === $user->id;
    }

    /**
     * Notify band leadership (owner and admins) about membership changes.
     */
    protected function notifyBandLeadership(Band $band, User $user, string $action): void
    {
        if ($action !== 'accepted') {
            return; // Only notify on acceptance for now
        }

        $adminsAndOwner = $band->activeMembers()
            ->wherePivot('role', 'admin')
            ->get()
            ->push($band->owner)
            ->unique('id')
            ->filter(fn ($u) => $u->id !== $user->id); // Don't notify the person who just joined

        foreach ($adminsAndOwner as $admin) {
            $admin->notify(new BandInvitationAcceptedNotification($band, $user));
        }
    }

    /**
     * Check if a band name conflicts with an existing guest band (no owner).
     */
    public function findClaimableBand(string $bandName): ?Band
    {
        return Band::where('name', 'ilike', $bandName)
            ->whereNull('owner_id')
            ->first();
    }

    /**
     * Claim ownership of a guest band.
     */
    public function claimBand(Band $band, User $user): bool
    {
        if ($band->owner_id) {
            return false; // Band already has an owner
        }

        return DB::transaction(function () use ($band, $user) {
            // Update band ownership
            $band->update([
                'owner_id' => $user->id,
                'status' => 'active'
            ]);

            // Add user as owner/admin member
            if (!$this->hasMember($band, $user)) {
                $this->addMember($band, $user, 'admin');
            } else {
                // Update existing membership to admin
                $this->updateMemberRole($band, $user, 'admin');
            }

            // Notify admins about the band being claimed
            $admins = User::role(['admin', 'super admin'])->get();
            Notification::send($admins, new BandClaimedNotification($band, $user));

            return true;
        });
    }

    /**
     * Get similar band names for suggestion purposes.
     */
    public function getSimilarBandNames(string $bandName, int $limit = 5): Collection
    {
        return Band::where('name', 'ilike', "%{$bandName}%")
            ->whereNull('owner_id')
            ->limit($limit)
            ->pluck('name', 'id');
    }

    /**
     * Check if user can claim a specific band.
     */
    public function canClaimBand(Band $band, User $user): bool
    {
        // Band must not have an owner
        if ($band->owner_id) {
            return false;
        }

        // User must have permission to create bands
        if (!$user->can('create bands')) {
            return false;
        }

        return true;
    }
}
