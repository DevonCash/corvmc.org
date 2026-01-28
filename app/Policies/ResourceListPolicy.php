<?php

namespace App\Policies;

use App\Models\ResourceList;
use App\Models\User;

class ResourceListPolicy
{
    /**
     * Determine if the user can manage resource lists (admin only).
     */
    public function manage(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can view any resource lists.
     * Resource lists page is public.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the resource list.
     * Individual resource lists are publicly viewable.
     */
    public function view(?User $user, ResourceList $resourceList): bool
    {
        return true;
    }

    /**
     * Determine if the user can create resource lists.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can update the resource list.
     */
    public function update(User $user, ResourceList $resourceList): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can delete the resource list.
     */
    public function delete(User $user, ResourceList $resourceList): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can restore the resource list.
     */
    public function restore(User $user, ResourceList $resourceList): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can permanently delete the resource list.
     * Force deletion is never allowed.
     */
    public function forceDelete(User $user, ResourceList $resourceList): bool
    {
        return false;
    }
}
