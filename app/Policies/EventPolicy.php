<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Moderation\Enums\Visibility;

class EventPolicy
{
    public function manage(User $user, ?Event $event = null): ?bool
    {
        if ($user->hasRole('production manager')) {
            return true;
        }

        if ($event && $event->isOrganizedBy($user)) {
            return true;
        }
        return null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Event $event): ?bool
    {
        // Unpublished events: only managers or organizer
        if (! $event->isPublished()) {
            return $user && $this->manage($user, $event);
        }

        // Public events visible to everyone (including guests)
        if ($event->visibility === Visibility::Public) {
            return true;
        }

        // All other visibility levels require authentication
        if (! $user) {
            return false;
        }

        // Managers or organizer can view all visibility levels
        if ($this->manage($user, $event)) {
            return true;
        }

        // Members visibility = any logged-in user
        if ($event->visibility === Visibility::Members) {
            return true;
        }

        // Private = only organizer/manager (already checked above)
        return false;
    }

    public function create(User $user): ?bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Event $event): ?bool
    {
        return $this->manage($user, $event);
    }

    public function delete(User $user, Event $event): ?bool
    {
        return $this->manage($user, $event);
    }

    public function restore(User $user, Event $event): bool
    {
        return $this->manage($user, $event);
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return false;
    }

    // Domain-specific methods
    public function publish(User $user, Event $event): ?bool
    {
        // Event must be publishable (has title, etc.)
        if (! $event->canPublish()) {
            return false;
        }

        return $this->update($user, $event);
    }

    public function cancel(User $user, Event $event): ?bool
    {
        return $this->manage($user, $event);
    }

    public function reschedule(User $user, Event $event): ?bool
    {
        return $this->cancel($user, $event);
    }

    public function managePerformers(User $user, Event $event): ?bool
    {
        return $this->update($user, $event);
    }
}
