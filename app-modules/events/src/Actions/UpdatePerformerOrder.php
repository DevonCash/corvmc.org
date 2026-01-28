<?php

namespace CorvMC\Events\Actions;

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePerformerOrder
{
    use AsAction;

    /**
     * Update a performer's order in the event lineup.
     *
     * @param  Event  $event  The event
     * @param  Band  $band  The band whose order to update
     * @param  int  $order  The new order position
     * @return bool True if order was updated, false if band not found
     */
    public function handle(Event $event, Band $band, int $order): bool
    {
        // Check if the band is a performer
        if (! $event->performers()->where('band_profile_id', $band->id)->exists()) {
            return false;
        }

        // Update the order
        $event->performers()->updateExistingPivot($band->id, ['order' => $order]);

        return true;
    }
}
