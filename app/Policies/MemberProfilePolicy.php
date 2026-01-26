<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Moderation\Enums\Visibility;

class MemberProfilePolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('directory moderator');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, MemberProfile $memberProfile): bool
    {
        // Public profiles visible to everyone (including guests)
        if ($memberProfile->visibility === Visibility::Public) {
            return true;
        }

        // All other visibility levels require authentication
        if (! $user) {
            return false;
        }

        // Managers can view all profiles
        if ($this->manage($user)) {
            return true;
        }

        // Owner can always view their own profile
        if ($memberProfile->isOwnedByUser($user)) {
            return true;
        }

        // Members visibility = any logged-in CMC member
        if ($memberProfile->visibility === Visibility::Members) {
            return true;
        }

        // Private = only owner (already checked above)
        return false;
    }

    public function create(User $user): bool
    {
        return true; // All authenticated users can create their profile
    }

    public function update(User $user, MemberProfile $memberProfile): bool
    {
        return $this->manage($user) || $memberProfile->isOwnedByUser($user);
    }

    public function delete(User $user, MemberProfile $memberProfile): bool
    {
        return $this->manage($user) || $memberProfile->isOwnedByUser($user);
    }

    public function restore(User $user, MemberProfile $memberProfile): bool
    {
        return $this->delete($user, $memberProfile);
    }

    public function forceDelete(User $user, MemberProfile $memberProfile): bool
    {
        return false; // Never allowed
    }

    public function viewContact(?User $user, MemberProfile $memberProfile): bool
    {
        $visibility = $memberProfile->contact?->visibility;

        // No contact info or public - anyone can view
        if (! $visibility || $visibility === Visibility::Public) {
            return true;
        }

        // Guest users can only see public contacts
        if (! $user) {
            return false;
        }

        // Owner can always view their own contact info
        if ($memberProfile->isOwnedByUser($user)) {
            return true;
        }

        // Managers can view all contact info
        if ($this->manage($user)) {
            return true;
        }

        // Members visibility = any logged-in CMC member
        if ($visibility === Visibility::Members) {
            return true;
        }

        // Private = only owner or manager (already checked above)
        return false;
    }
}
