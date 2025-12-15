<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEventStatus
{
    use AsAction;

    /**
     * Update an event's status.
     *
     * @param  Event  $event  The event to update
     * @param  EventStatus  $status  The new status
     * @return Event The updated event
     */
    public function handle(Event $event, EventStatus $status): Event
    {
        $event->update([
            'status' => $status,
        ]);

        // Future: Add notification logic based on status change
        // - Notify when event goes to AtCapacity (sold out)
        // - Notify when event becomes available again
        // - Other status-specific notifications

        return $event->fresh();
    }
}
