<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Models\Event;
use App\Models\User;
use CorvMC\Events\Notifications\EventPublishedNotification;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishEvent
{
    use AsAction;

    /**
     * Publish an event.
     */
    public function handle(Event $event): void
    {
        // Check authorization
        if (! User::me()?->can('publish', $event)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to publish this event.');
        }

        $event->publish();

        // Notify performers and stakeholders
        $users = GetInterestedUsers::run($event);
        if ($users->isNotEmpty()) {
            Notification::send($users, new EventPublishedNotification($event));
        }
    }
}
