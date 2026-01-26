<?php

namespace App\Policies;

use App\Models\LocalResource;
use App\Models\User;

class LocalResourcePolicy
{
    /**
     * Determine if the user can manage local resources (admin only).
     */
    public function manage(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can view any local resources.
     * Local resources are publicly viewable.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the local resource.
     * Individual local resources are publicly viewable.
     */
    public function view(?User $user, LocalResource $localResource): bool
    {
        return true;
    }

    /**
     * Determine if the user can create local resources.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can update the local resource.
     */
    public function update(User $user, LocalResource $localResource): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can delete the local resource.
     */
    public function delete(User $user, LocalResource $localResource): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can restore the local resource.
     */
    public function restore(User $user, LocalResource $localResource): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can permanently delete the local resource.
     * Force deletion is never allowed.
     */
    public function forceDelete(User $user, LocalResource $localResource): bool
    {
        return false;
    }
}
