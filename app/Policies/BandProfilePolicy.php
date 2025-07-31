<?php

namespace App\Policies;

use App\Models\BandProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BandProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BandProfile $band): ?bool
    {
        if ($band->isPublic() || $band->members()->contains($user))
            return true;
        return null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BandProfile $band): ?bool
    {
        if ($user->can('update', ['band_id' => $band->id])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BandProfile $bandProfile): ?bool
    {
        if ($user->id === $bandProfile->owner->id || $user->can('delete profiles')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BandProfile $bandProfile): ?bool
    {
        if ($user->id === $bandProfile->owner->id || $user->can('restore profiles')) {
            return true;
        }
        return null;
    }


    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BandProfile $bandProfile): ?bool
    {
        if ($user->can('force delete profiles') || $user->id === $bandProfile->owner->id) {
            return true;
        }
        return null;
    }
}
