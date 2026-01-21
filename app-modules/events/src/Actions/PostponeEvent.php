<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class PostponeEvent
{
    use AsAction;

    /**
     * Postpone an event (without setting a new date).
     *
     * @param  Event  $event  The event to postpone
     * @param  string|null  $reason  Optional postponement reason
     * @return Event The updated event
     */
    public function handle(Event $event, ?string $reason = null): Event
    {
        $event->update([
            'status' => EventStatus::Postponed,
            'reschedule_reason' => $reason,
        ]);

        // Future: Add notification logic here
        // - Notify event organizer
        // - Notify performers
        // - Notify attendees (if we track them)

        return $event->fresh();
    }
}
