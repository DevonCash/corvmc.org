<?php

namespace App\Policies;

use App\Models\Band;
use App\Models\User;

class BandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Band $band): ?bool
    {
        // Public bands are viewable by anyone
        if ($band->visibility === 'public') {
            return true;
        }

        // Members-only bands are viewable by authenticated users
        if ($band->visibility === 'members') {
            return true;
        }

        // Private bands are only viewable by members and owners
        if ($band->visibility === 'private') {
            return $this->isMemberOrOwner($user, $band);
        }

        return null;
    }

    public function create(User $user): bool
    {
        // Check if user has permission to create bands
        if ($user->can('create bands')) {
            return true;
        }

        // Check if band creation requires approval
        if ($user->can('approve band creation')) {
            return true;
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Band $band): bool
    {
        // Owner can always update
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Check if user has permission to update bands
        if ($user->can('update bands')) {
            return true;
        }

        // System admins and moderators can update
        if ($user->hasRole(['admin', 'moderator'])) {
            return true;
        }

        // Band admins can update
        return $this->isBandAdmin($user, $band);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Band $band): ?bool
    {
        if ($user->id === $band->owner->id || $user->can('delete bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Band $band): ?bool
    {
        if ($user->id === $band->owner->id || $user->can('restore bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Band $band): bool
    {
        return $user->hasRole(['admin']) || $user->can('force delete bands');
    }

    /**
     * Determine whether the user can manage band members.
     */
    public function manageMembers(User $user, Band $band): bool
    {
        // Owner can manage members
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Check if user has permission to manage band members
        if ($user->can('manage band members')) {
            return true;
        }

        // Band admins can manage members
        return $this->isBandAdmin($user, $band);
    }

    /**
     * Determine whether the user can invite members to the band.
     */
    public function inviteMembers(User $user, Band $band): bool
    {
        // Check specific invite permission
        if ($user->can('invite band members')) {
            return true;
        }

        return $this->manageMembers($user, $band);
    }

    /**
     * Determine whether the user can remove members from the band.
     */
    public function removeMembers(User $user, Band $band): bool
    {
        // Check specific remove permission
        if ($user->can('remove band members')) {
            return true;
        }

        return $this->manageMembers($user, $band);
    }

    /**
     * Determine whether the user can change member roles.
     */
    public function changeMemberRoles(User $user, Band $band): bool
    {
        // Check specific permission
        if ($user->can('change member roles')) {
            return true;
        }

        // Only owner can change roles
        return $band->owner_id === $user->id;
    }

    /**
     * Determine whether the user can leave the band.
     */
    public function leave(User $user, Band $band): bool
    {
        // Owner cannot leave their own band (must transfer ownership first)
        if ($band->owner_id === $user->id) {
            return null;
        }

        // Members can leave
        return $this->isMember($user, $band);
    }

    /**
     * Determine whether the user can transfer ownership of the band.
     */
    public function transferOwnership(User $user, Band $band): bool
    {
        // Check specific permission
        if ($user->can('transfer band ownership')) {
            return true;
        }

        return $band->owner_id === $user->id;
    }

    /**
     * Helper method to check if user is a member or owner of the band.
     */
    protected function isMemberOrOwner(User $user, Band $band): bool
    {
        return $band->owner_id === $user->id || $this->isMember($user, $band);
    }

    /**
     * Helper method to check if user is a member of the band.
     */
    protected function isMember(User $user, Band $band): bool
    {
        return $band->members()->wherePivot('user_id', $user->id)->exists();
    }

    /**
     * Helper method to check if user is a band admin.
     */
    protected function isBandAdmin(User $user, Band $band): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function viewMembers(User $user, Band $band): bool
    {
        // Public bands: anyone can view members
        if ($band->visibility === 'public') {
            return true;
        }

        // Members-only bands: only members and owner can view members
        if ($band->visibility === 'members') {
            return $this->isMemberOrOwner($user, $band);
        }

        // Private bands: only members and owner can view members
        if ($band->visibility === 'private') {
            return $this->isMemberOrOwner($user, $band);
        }

        return false;
    }

    public function viewContact(?User $user, Band $band): bool
    {
        // Public bands: anyone can view contact info
        if ($band->visibility === 'public') {
            return true;
        }

        // Members-only bands: only members and owner can view contact info
        if ($band->visibility === 'members') {
            return $user !== null;
        }

        // Private bands: only members and owner can view contact info
        if ($band->visibility === 'private') {
            return $this->isMemberOrOwner($user, $band);
        }

        return false;
    }
}
