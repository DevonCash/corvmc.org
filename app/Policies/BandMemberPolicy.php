<?php

namespace App\Policies;

use App\Models\BandProfile;
use App\Models\User;

class BandMemberPolicy
{
    /**
     * Determine whether the user can view band members.
     */
    public function viewMembers(User $user, BandProfile $band): bool
    {
        // Public bands - anyone can see members
        if ($band->visibility === 'public') {
            return true;
        }

        // Members-only bands - authenticated users can see members
        if ($band->visibility === 'members') {
            return true;
        }

        // Private bands - only members can see other members
        if ($band->visibility === 'private') {
            return $this->isMemberOrOwner($user, $band);
        }

        return null;
    }

    /**
     * Determine whether the user can join the band.
     */
    public function join(User $user, BandProfile $band): bool
    {
        // Can't join if already a member
        if ($this->isMemberOrOwner($user, $band)) {
            return null;
        }

        // Can join public bands or if invited
        return $band->visibility === 'public' || $this->hasInvitation($user, $band);
    }

    /**
     * Determine whether the user can leave the band.
     */
    public function leave(User $user, BandProfile $band): bool
    {
        // Owner cannot leave (must transfer ownership first)
        if ($band->owner_id === $user->id) {
            return null;
        }

        // Must be a member to leave
        return $this->isMember($user, $band);
    }

    /**
     * Determine whether the user can invite others to the band.
     */
    public function invite(User $user, BandProfile $band): bool
    {
        // Owner can invite
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Band admins can invite
        return $this->isBandAdmin($user, $band);
    }

    /**
     * Determine whether the user can remove a specific member.
     */
    public function removeMember(User $user, BandProfile $band, User $targetMember): bool
    {
        // Cannot remove yourself (use leave instead)
        if ($user->id === $targetMember->id) {
            return null;
        }

        // Cannot remove the owner
        if ($band->owner_id === $targetMember->id) {
            return null;
        }

        // Owner can remove anyone
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Band admins can remove regular members (but not other admins)
        if ($this->isBandAdmin($user, $band)) {
            return !$this->isBandAdmin($targetMember, $band);
        }

        return null;
    }

    /**
     * Determine whether the user can change a member's role.
     */
    public function changeRole(User $user, BandProfile $band, User $targetMember): bool
    {
        // Only owner can change roles
        if ($band->owner_id !== $user->id) {
            return null;
        }

        // Cannot change own role
        if ($user->id === $targetMember->id) {
            return null;
        }

        // Target must be a member
        return $this->isMember($targetMember, $band);
    }

    /**
     * Determine whether the user can change a member's position.
     */
    public function changePosition(User $user, BandProfile $band, User $targetMember): bool
    {
        // Owner can change anyone's position
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Band admins can change positions
        if ($this->isBandAdmin($user, $band)) {
            return true;
        }

        // Members can change their own position
        return $user->id === $targetMember->id;
    }

    /**
     * Determine whether the user can view member contact info.
     */
    public function viewMemberContact(User $user, BandProfile $band, User $targetMember): bool
    {
        // Can always view own contact info
        if ($user->id === $targetMember->id) {
            return true;
        }

        // Owner and admins can view contact info
        if ($band->owner_id === $user->id || $this->isBandAdmin($user, $band)) {
            return true;
        }

        // Regular members can view each other's contact if both are in the band
        return $this->isMember($user, $band) && $this->isMember($targetMember, $band);
    }

    /**
     * Helper method to check if user is a member or owner of the band.
     */
    protected function isMemberOrOwner(User $user, BandProfile $band): bool
    {
        return $band->owner_id === $user->id || $this->isMember($user, $band);
    }

    /**
     * Helper method to check if user is a member of the band.
     */
    protected function isMember(User $user, BandProfile $band): bool
    {
        return $band->members()->wherePivot('user_id', $user->id)->exists();
    }

    /**
     * Helper method to check if user is a band admin.
     */
    protected function isBandAdmin(User $user, BandProfile $band): bool
    {
        return $band->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Helper method to check if user has an invitation to the band.
     * This would connect to a band invitations system if implemented.
     */
    protected function hasInvitation(User $user, BandProfile $band): bool
    {
        // TODO: Implement band invitation system
        // For now, return false - invitations would be handled separately
        return null;
    }
}