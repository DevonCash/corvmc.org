<?php

namespace App\Actions\Events;

use App\Models\Band;
use App\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePerformerSetLength
{
    use AsAction;

    /**
     * Update a performer's set length.
     *
     * @param Event $event The event
     * @param Band $band The band whose set length to update
     * @param int $setLength The new set length in minutes
     * @return bool True if set length was updated, false if band not found
     */
    public function handle(Event $event, Band $band, int $setLength): bool
    {
        // Check if the band is a performer
        if (! $event->performers()->where('band_profile_id', $band->id)->exists()) {
            return false;
        }

        // Update the set length
        $event->performers()->updateExistingPivot($band->id, ['set_length' => $setLength]);

        return true;
    }
}
