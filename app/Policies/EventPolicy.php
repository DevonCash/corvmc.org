<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Events\Models\Event;

class EventPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('production manager');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        if ($event->isPublished()) {
            return true;
        }

        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    public function restore(User $user, Event $event): bool
    {
        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return false;
    }

    // Domain-specific methods
    public function publish(User $user, Event $event): bool
    {
        return $this->update($user, $event) && $event->canPublish();
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    public function postpone(User $user, Event $event): bool
    {
        return $this->cancel($user, $event);
    }

    public function reschedule(User $user, Event $event): bool
    {
        return $this->cancel($user, $event);
    }

    public function managePerformers(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
