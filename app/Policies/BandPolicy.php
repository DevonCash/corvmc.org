<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Moderation\Enums\Visibility;

class BandPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('directory moderator');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Band $band): bool
    {
        // Public bands visible to everyone (including guests)
        if ($band->visibility === Visibility::Public) {
            return true;
        }

        // All other visibility levels require authentication
        if (! $user) {
            return false;
        }

        // Managers can view all bands
        if ($this->manage($user)) {
            return true;
        }

        // Band members can always view
        if ($band->isMember($user)) {
            return true;
        }

        // Members visibility = any logged-in CMC member
        if ($band->visibility === Visibility::Members) {
            return true;
        }

        // Private = only band members (already checked above)
        return false;
    }

    public function create(User $user): bool
    {
        return true; // All authenticated users can create
    }

    public function update(User $user, Band $band): bool
    {
        return $this->manage($user) || $band->isAdmin($user);
    }

    public function delete(User $user, Band $band): bool
    {
        return $this->manage($user) || $band->isOwner($user);
    }

    public function restore(User $user, Band $band): bool
    {
        return $this->delete($user, $band);
    }

    public function forceDelete(User $user, Band $band): bool
    {
        return false; // Never allowed
    }

    // Domain-specific methods
    public function invite(User $user, Band $band): bool
    {
        return $this->manage($user) || $band->isAdmin($user);
    }

    public function transfer(User $user, Band $band): bool
    {
        return $band->isOwner($user);
    }

    public function contact(?User $user, Band $band): bool
    {
        $visibility = $band->contact?->visibility;

        // No contact info or public - anyone can view
        if (! $visibility || $visibility === Visibility::Public) {
            return true;
        }

        // Guest users can only see public contacts
        if (! $user) {
            return false;
        }

        // Members visibility = any logged-in CMC member
        if ($visibility === Visibility::Members) {
            return true;
        }

        // Private = band members only
        return $band->isMember($user);
    }

    public function join(User $user, Band $band): bool
    {
        return $band->membership($user)?->status === 'invited';
    }

    public function leave(User $user, Band $band): bool
    {
        if ($band->isOwner($user)) {
            return false; // Owner must transfer first
        }

        return $band->isMember($user);
    }

    public function manageMembers(User $user, Band $band): bool
    {
        return $this->invite($user, $band);
    }
}
