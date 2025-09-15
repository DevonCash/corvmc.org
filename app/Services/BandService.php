<?php

namespace App\Services;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use App\Notifications\BandClaimedNotification;
use App\Notifications\BandInvitationAcceptedNotification;
use App\Notifications\BandInvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;

class BandService
{

    /**
     * Create a new band with proper validation and notifications.
     */
    public function createBand(array $data): Band
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            if (!$user?->can('create', Band::class)) {
                throw new UnauthorizedException('User does not have permission to create bands.');
            }

            // Set owner to current user if not specified
            if (!isset($data['owner_id'])) {
                $data['owner_id'] = $user->id;
            }

            $band = Band::create($data);

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $band->attachTags($data['tags']);
            }

            // Add the creator as a member if they're not already
            if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
                $this->addMember($band, $user, ['role' => 'owner']);
            }

            return $band;
        });
    }

    /**
     * Update a band with validation and notifications.
     */
    public function updateBand(Band $band, array $data): Band
    {
        return DB::transaction(function () use ($band, $data) {
            $band->update($data);

            // Update tags if provided
            if (isset($data['tags'])) {
                $band->syncTags($data['tags']);
            }

            return $band->fresh();
        });
    }

    /**
     * Delete a band with proper cleanup.
     */
    public function deleteBand(Band $band): bool
    {
        return DB::transaction(function () use ($band) {
            // Notify members about band deletion
            $members = $band->members()->get();
            foreach ($members as $member) {
                // You could send a BandDeletedNotification here
            }

            // Remove from any productions
            // $band->productions()->detach();

            return $band->delete();
        });
    }

    /**
     * Invite a user to join a band.
     */
    public function inviteMember(
        Band $band,
        User $user,
        string $role = 'member',
        ?string $position = null,
        ?string $displayName = null
    ): void {
        if ($band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        if ($band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyInvited();
        }

        DB::transaction(function () use ($band, $user, $role, $position, $displayName) {
            // Add invitation to pivot table
            $band->members()->attach($user->id, [
                'role' => $role,
                'position' => $position,
                'name' => $displayName ?? $user->name,
                'status' => 'invited',
                'invited_at' => now(),
            ]);
        });
        // Send notification
        $user->notify(new BandInvitationNotification($band, $role, $position));
    }

    /**
     * Accept an invitation to join a band.
     */
    public function acceptInvitation(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        DB::transaction(function () use ($band, $user) {
            // Update status to active
            $band->members()->updateExistingPivot($user->id, [
                'status' => 'active',
            ]);

            // Notify band owner and admins about the new member
            $this->notifyBandLeadership($band, $user, 'accepted');
        });
    }

    /**
     * Decline an invitation to join a band.
     */
    public function declineInvitation(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        $band->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);
    }

    /**
     * Add a member directly to a band (without invitation).
     */

    public function addMember(
        Band $band,
        ?User $user = null,
        array $data = [],
    ): void {
        // Check if user is already a member by looking at pivot table
        if ($user && $band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        $role = $data['role'] ?? 'member';
        $position = $data['position'] ?? null;
        $displayName = $data['display_name'] ?? null;

        DB::transaction(function () use ($band, $user, $role, $position, $displayName) {
            // If user is null, create a guest member entry (non-CMC member)
            if (is_null($user)) {
                BandMember::create([
                    'band_profile_id' => $band->id,
                    'user_id' => null,
                    'name' => $displayName ?? 'Guest Member',
                    'role' => $role,
                    'position' => $position,
                    'status' => 'active',
                    'invited_at' => now(),
                ]);
            } else {
                // Add member to pivot table (for tracking purposes)
                $band->members()->attach($user->id, [
                    'role' => $role,
                    'position' => $position,
                    'name' => $displayName ?? $user->name,
                    'status' => 'active',
                    'invited_at' => now(),
                ]);

                // Grant appropriate scoped permissions based on role
                $this->grantPermissionsForRole($user, $band, $role);
            }
        });
    }

    /**
     * Remove a member from a band.
     */
    public function removeMember(Band $band, User $user): void
    {
        // Cannot remove the owner
        if ($band->owner_id === $user->id) {
            throw BandException::cannotRemoveOwner();
        }

        // Check if user is a member by looking at pivot table
        if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotMember();
        }

        DB::transaction(function () use ($band, $user) {
            // Remove from pivot table
            $band->members()->detach($user->id);

            // Revoke all band-scoped permissions
            $this->revokeAllBandPermissions($user, $band);
        });
    }

    /**
     * Allow a user to leave a band they are a member of.
     */
    public function leaveBand(Band $band, User $user): void
    {
        // Owner cannot leave their own band
        if ($band->owner_id === $user->id) {
            throw BandException::cannotLeaveOwnedBand();
        }

        // Check if user is a member by looking at pivot table
        if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotMember();
        }

        DB::transaction(function () use ($band, $user) {
            // Remove from pivot table
            $band->members()->detach($user->id);

            // Revoke all band-scoped permissions
            $this->revokeAllBandPermissions($user, $band);
        });
    }

    /**
     * Update a member's role in the band.
     */
    public function updateMemberRole(Band $band, User $user, string $role): void
    {
        // Cannot change owner's role
        if ($band->owner_id === $user->id) {
            throw BandException::cannotChangeOwnerRole();
        }

        // Check if user is a member by looking at pivot table
        if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotMember();
        }

        DB::transaction(function () use ($band, $user, $role) {
            // Update pivot table role
            $band->members()->updateExistingPivot($user->id, ['role' => $role]);

            // Revoke existing permissions and grant new ones
            $this->revokeAllBandPermissions($user, $band);
            $this->grantPermissionsForRole($user, $band, $role);
        });
    }

    /**
     * Update a member's position in the band.
     */
    public function updateMemberPosition(Band $band, User $user, ?string $position): void
    {
        // TODO: Add authorization check - only band owner/admins should be able to update positions

        if (! $band->memberships()->active()->for($user)->exists()) {
            throw BandException::userNotMember();
        }

        $band->members()->updateExistingPivot($user->id, ['position' => $position]);
    }

    /**
     * Update a member's display name in the band.
     */
    public function updateMemberDisplayName(Band $band, User $user, ?string $displayName): void
    {
        // TODO: Add authorization check - only band owner/admins should be able to update display names

        if (! $band->memberships()->active()->for($user)->exists()) {
            throw BandException::userNotMember();
        }

        $band->members()->updateExistingPivot($user->id, ['name' => $displayName]);
    }

    /**
     * Resend an invitation to a user.
     */
    public function resendInvitation(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->for($user)->exists()) {
            throw BandException::userNotInvited();
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
    }

    /**
     * Cancel a pending invitation to a user.
     */
    public function cancelInvitation(Band $band, User $user): void
    {
        if (!$band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        // Remove the invitation from the pivot table
        $band->members()->detach($user->id);
    }

    /**
     * Re-invite a user who previously declined.
     */
    public function reInviteDeclinedUser(Band $band, User $user): void
    {
        if (! $band->memberships()->declined()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotDeclined();
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
    }

    /**
     * Transfer ownership of a band to another member.
     */
    public function transferOwnership(Band $band, User $newOwner): void
    {
        if (! $band->memberships()->active()->for($newOwner)->exists()) {
            throw BandException::userNotMember();
        }

        DB::transaction(function () use ($band, $newOwner) {
            $oldOwner = $band->owner;

            // Update band ownership
            $band->update(['owner_id' => $newOwner->id]);

            // Add old owner as admin if they weren't already a member
            if (! $band->memberships()->active()->for($oldOwner)->exists()) {
                $this->addMember($band, $oldOwner, ['role' => 'admin']);
            } else {
                // Update their role to admin
                $band->memberships()->for($oldOwner)->update(['role' => 'admin']);
            }
        });
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
            ->where('id', '!=', $band->owner_id) // Exclude band owner
            ->whereDoesntHave(
                'Bands',
                fn($query) => $query->where('band_profile_id', $band->id)
            )
            ->limit(50)
            ->get();
    }


    /**
     * Notify band leadership (owner and admins) about membership changes.
     */
    protected function notifyBandLeadership(Band $band, User $user, string $action): void
    {
        if ($action !== 'accepted') {
            return; // Only notify on acceptance for now
        }

        $adminMembers = $band->memberships()
            ->active()
            ->where('role', 'admin')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $adminsAndOwner = $adminMembers
            ->push($band->owner)
            ->unique('id')
            ->filter(fn($u) => $u->id !== $user->id); // Don't notify the person who just joined

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
    public function claimBand(Band $band, User $user): void
    {
        if ($band->owner_id) {
            throw BandException::bandAlreadyHasOwner();
        }

        if (!$user->can('create bands')) {
            throw new UnauthorizedException('User does not have permission to claim bands.');
        }

        DB::transaction(function () use ($band, $user) {
            // Update band ownership
            $band->update([
                'owner_id' => $user->id,
                'status' => 'active'
            ]);

            // Add user as owner/admin member
            if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
                $this->addMember($band, $user, ['role' => 'admin']);
            } else {
                // Update existing membership to admin
                $band->memberships()->for($user)->update(['role' => 'admin']);
            }

            // Notify admins about the band being claimed
            $admins = User::role(['admin'])->get();
            Notification::send($admins, new BandClaimedNotification($band, $user));
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
     * Create a band for an existing user.
     */
    public function createBandForUser(User $user, string $bandName, array $bandData = []): Band
    {
        return DB::transaction(function () use ($user, $bandName, $bandData) {
            $band = Band::create(array_merge([
                'name' => $bandName,
                'owner_id' => $user->id,
                'visibility' => 'members',
                'status' => 'active',
            ], $bandData));

            // Add the user as a member with 'owner' role in the pivot
            $band->members()->attach($user->id, [
                'role' => 'owner',
                'status' => 'active',
            ]);

            return $band;
        });
    }

    /**
     * Confirm band ownership from invitation data.
     */
    public function confirmBandOwnershipFromInvitation(User $user, array $invitationData): void
    {
        if (!isset($invitationData['band_id'])) {
            throw new InvalidArgumentException('Invitation data does not contain band_id.');
        }

        $band = Band::withoutGlobalScopes()->findOrFail($invitationData['band_id']);

        if ($band->status !== 'pending_owner_verification') {
            throw new InvalidArgumentException('Band is not pending owner verification.');
        }

        // Add user as band member with owner role
        $band->members()->attach($user->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $band->update([
            'status' => 'active',
            'owner_id' => $user->id
        ]);

        // Grant owner permissions
        $this->grantPermissionsForRole($user, $band, 'owner');
    }

    /**
     * Grant permissions to a user based on their role in the band.
     * Note: Individual band permissions have been removed - authorization is now context-based.
     */
    private function grantPermissionsForRole(User $user, Band $band, string $role): void
    {
        // No individual permissions to grant - authorization is now context-based through policies
        // The user's role is stored in the pivot table and checked by policies
    }

    /**
     * Revoke all band-scoped permissions from a user.
     * Note: Individual band permissions have been removed - authorization is now context-based.
     */
    private function revokeAllBandPermissions(User $user, Band $band): void
    {
        // No individual permissions to revoke - authorization is now context-based through policies
        // Removing the user from the pivot table is sufficient
    }
}
