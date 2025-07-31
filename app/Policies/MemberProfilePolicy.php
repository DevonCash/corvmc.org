<?php

namespace App\Policies;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        if ($memberProfile->isVisible($user)) return true;
        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
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
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MemberProfile $memberProfile): ?bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MemberProfile $memberProfile): ?bool
    {
        return false;
    }
}
