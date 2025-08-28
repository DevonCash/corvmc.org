<?php

namespace App\Policies;

use App\Models\MemberProfile;
use App\Models\User;

class MemberProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MemberProfile $memberProfile): ?bool
    {
        if ($memberProfile->isVisible($user)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Members can create their own profile (handled in registration)
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MemberProfile $memberProfile): ?bool
    {
        if ($user->id === $memberProfile->user->id || $user->can('update member profiles')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MemberProfile $memberProfile): ?bool
    {
        // Users can delete their own profile or admins can delete
        if ($user->id === $memberProfile->user->id || $user->can('delete member profiles')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MemberProfile $memberProfile): ?bool
    {
        // Users can restore their own profile or admins can restore
        if ($user->id === $memberProfile->user->id || $user->can('restore member profiles')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MemberProfile $memberProfile): ?bool
    {
        return false;
    }

    public function viewContact(User $user, MemberProfile $memberProfile): bool
    {
        // Users can view their own contact info
        if ($user->id === $memberProfile->user->id) {
            return true;
        }

        if ($memberProfile->contact_visibility === 'public') {
            return true;
        }

        if ($memberProfile->contact_visibility === 'members' && $user->isMember()) {
            return true;
        }

        return false;
    }
}
