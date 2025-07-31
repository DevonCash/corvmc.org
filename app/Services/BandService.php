<?php

namespace App\Services;

use App\Models\BandProfile;
use App\Models\User;
use App\Notifications\BandInvitationAcceptedNotification;
use App\Notifications\BandInvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BandService
{
    /**
     * Invite a user to join a band.
     */
    public function inviteMember(
        BandProfile $band, 
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
    public function acceptInvitation(BandProfile $band, User $user): bool
    {
        if (!$this->hasInvitedUser($band, $user)) {
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
    public function declineInvitation(BandProfile $band, User $user): bool
    {
        if (!$this->hasInvitedUser($band, $user)) {
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
        BandProfile $band, 
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
    public function removeMember(BandProfile $band, User $user): bool
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
    public function updateMemberRole(BandProfile $band, User $user, string $role): bool
    {
        // Cannot change owner's role
        if ($band->owner_id === $user->id) {
            return false;
        }

        if (!$this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['role' => $role]);
        return true;
    }

    /**
     * Update a member's position in the band.
     */
    public function updateMemberPosition(BandProfile $band, User $user, ?string $position): bool
    {
        if (!$this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['position' => $position]);
        return true;
    }

    /**
     * Update a member's display name in the band.
     */
    public function updateMemberDisplayName(BandProfile $band, User $user, ?string $displayName): bool
    {
        if (!$this->hasMember($band, $user)) {
            return false;
        }

        $band->members()->updateExistingPivot($user->id, ['name' => $displayName]);
        return true;
    }

    /**
     * Resend an invitation to a user.
     */
    public function resendInvitation(BandProfile $band, User $user): bool
    {
        if (!$this->hasInvitedUser($band, $user)) {
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
     * Transfer ownership of a band to another member.
     */
    public function transferOwnership(BandProfile $band, User $newOwner): bool
    {
        if (!$this->hasMember($band, $newOwner)) {
            return false;
        }

        DB::transaction(function () use ($band, $newOwner) {
            $oldOwner = $band->owner;
            
            // Update band ownership
            $band->update(['owner_id' => $newOwner->id]);
            
            // Add old owner as admin if they weren't already a member
            if (!$this->hasMember($band, $oldOwner)) {
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
        return BandProfile::whereHas('members', function ($query) use ($user) {
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
    public function getAvailableUsersForInvitation(BandProfile $band, string $search = ''): Collection
    {
        return User::where('name', 'like', "%{$search}%")
            ->whereDoesntHave('bandProfiles', fn ($query) => 
                $query->where('band_profile_id', $band->id)
            )
            ->limit(50)
            ->get();
    }

    /**
     * Check if a user is a member of the band.
     */
    public function hasMember(BandProfile $band, User $user): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Check if a user has been invited to the band.
     */
    public function hasInvitedUser(BandProfile $band, User $user): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'invited')
            ->exists();
    }

    /**
     * Get a user's role in the band.
     */
    public function getUserRole(BandProfile $band, User $user): ?string
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
    public function hasAdmin(BandProfile $band, User $user): bool
    {
        return $this->getUserRole($band, $user) === 'admin';
    }

    /**
     * Check if a user is the owner of the band.
     */
    public function isOwner(BandProfile $band, User $user): bool
    {
        return $band->owner_id === $user->id;
    }

    /**
     * Notify band leadership (owner and admins) about membership changes.
     */
    protected function notifyBandLeadership(BandProfile $band, User $user, string $action): void
    {
        if ($action !== 'accepted') {
            return; // Only notify on acceptance for now
        }

        $adminsAndOwner = $band->activeMembers()
            ->wherePivot('role', 'admin')
            ->get()
            ->push($band->owner)
            ->unique('id')
            ->filter(fn($u) => $u->id !== $user->id); // Don't notify the person who just joined

        foreach ($adminsAndOwner as $admin) {
            $admin->notify(new BandInvitationAcceptedNotification($band, $user));
        }
    }
}