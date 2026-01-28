<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelEvent
{
    use AsAction;

    /**
     * Cancel an event.
     *
     * @param  Event  $event  The event to cancel
     * @param  string|null  $reason  Optional cancellation reason
     * @return Event The updated event
     */
    public function handle(Event $event, ?string $reason = null): Event
    {
        $data = [
            'status' => EventStatus::Cancelled,
        ];

        if ($reason !== null) {
            $data['cancellation_reason'] = $reason;
        }

        $event->update($data);

        // Future: Add notification logic here
        // - Notify event organizer
        // - Notify performers
        // - Notify attendees (if we track them)

        return $event->fresh();
    }
}
