<?php

namespace App\Policies;

use App\Models\Production;
use App\Models\User;

class ProductionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): ?bool
    {
        if ($user->can('view productions')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Production $production): ?bool
    {
        if ($user?->id === $production->manager_id || $user->can('manage productions')) {
            return true;
        }

        if ($production->isPublished()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): ?bool
    {
        if ($user->can('manage productions')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Production $production): ?bool
    {
        if ($user->can('manage productions') && $user->id === $production->manager_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Production $production): ?bool
    {
        if ($user->can('manage productions') && $user->id === $production->manager_id) {
            return true;
        }

        // Admins can delete any production
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Production $production): ?bool
    {
        if ($user->can('manage productions') && $user->id === $production->manager_id) {
            return true;
        }

        // Admins can restore any production
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Production $production): bool
    {
        return false;
    }
}
