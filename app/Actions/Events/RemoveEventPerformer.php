<?php

namespace App\Actions\Events;

use App\Models\Band;
use App\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveEventPerformer
{
    use AsAction;

    /**
     * Remove a performer (band) from an event.
     *
     * @param  Event  $event  The event to remove the performer from
     * @param  Band  $band  The band to remove
     * @return bool True if performer was removed, false if not found
     */
    public function handle(Event $event, Band $band): bool
    {
        // Check if the band is a performer
        if (! $event->performers()->where('band_profile_id', $band->id)->exists()) {
            return false;
        }

        // Detach the performer
        $event->performers()->detach($band->id);

        // Future: Add notification logic here
        // - Notify band members that they were removed from an event
        // - Notify event organizer

        return true;
    }
}
