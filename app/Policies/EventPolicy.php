<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): ?bool
    {
        if ($user->can('view events')) {
            return true;
        }

        return null;
    }

    public function manage(User $user, Event $event): ?bool
    {
        if ($user->can('manage events') && $user->id === $event->manager_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): ?bool
    {
        if ($user?->id === $event->manager_id || $user->can('manage events')) {
            return true;
        }

        if ($event->isPublished()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): ?bool
    {
        if ($user->can('manage events')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): ?bool
    {
        if ($user->can('manage events') && $user->id === $event->manager_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): ?bool
    {
        if ($user->can('manage events') && $user->id === $event->manager_id) {
            return true;
        }

        // Admins can delete any event
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): ?bool
    {
        if ($user->can('manage events') && $user->id === $event->manager_id) {
            return true;
        }

        // Admins can restore any event
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return false;
    }
}
