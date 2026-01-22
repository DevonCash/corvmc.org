<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Events\Models\Event;

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
        // Staff with 'manage events' permission can manage any staff event
        if ($user->can('manage events')) {
            return true;
        }

        // Community event organizers can manage their own events
        if ($event->organizer_id && $user->id === $event->organizer_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): ?bool
    {
        // Published events are visible to everyone
        if ($event->isPublished()) {
            return true;
        }

        // Staff can view all events
        if ($user->can('manage events')) {
            return true;
        }

        // Community event organizers can view their own events
        if ($event->organizer_id && $user->id === $event->organizer_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): ?bool
    {
        // Only staff with 'manage events' permission can create events for now
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
        // Staff with 'manage events' permission can update any event
        if ($user->can('manage events')) {
            return true;
        }

        // Community event organizers can update their own events
        if ($event->organizer_id && $user->id === $event->organizer_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): ?bool
    {
        // Staff with 'manage events' permission can delete any event
        if ($user->can('manage events')) {
            return true;
        }

        // Community event organizers can delete their own events
        if ($event->organizer_id && $user->id === $event->organizer_id) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): ?bool
    {
        // Staff with 'manage events' permission can restore any event
        if ($user->can('manage events')) {
            return true;
        }

        // Community event organizers can restore their own events
        if ($event->organizer_id && $user->id === $event->organizer_id) {
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
