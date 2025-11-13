<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetInterestedUsers
{
    use AsAction;

    /**
     * Get users who should be notified about event updates.
     * This includes: organizer, band members, and optionally all sustaining members for published events.
     */
    public function handle(Event $event): Collection
    {
        $users = User::query()->whereNull('id')->get(); // Start with empty EloquentCollection

        // Always notify the event organizer (if community event)
        if ($event->organizer) {
            $users = $users->merge([$event->organizer]);
        }

        // Notify all band members performing in this event
        foreach ($event->performers as $band) {
            $bandMembers = $band->members()->get();
            $users = $users->merge($bandMembers);
        }

        // For published events, optionally notify all sustaining members
        if ($event->isPublished()) {
            $sustainingMembers = User::role('sustaining member')->get();
            $users = $users->merge($sustainingMembers);
        }

        // Remove duplicates and filter out null values
        return $users->filter()->unique('id');
    }
}
