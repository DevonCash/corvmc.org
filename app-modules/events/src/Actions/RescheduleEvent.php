<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RescheduleEvent
{
    use AsAction;

    /**
     * Reschedule an event to a new date/time, or postpone it (TBA).
     *
     * When $newEventData contains a start_datetime, a new event is created and
     * the original is linked to it. When no start_datetime is provided (TBA mode),
     * the original event is simply marked as Postponed.
     *
     * @param  Event  $originalEvent  The event to reschedule
     * @param  array  $newEventData  Data for the new event (optional start_datetime for TBA)
     * @param  string|null  $reason  Optional reason for rescheduling
     * @return Event The newly created event (if rescheduled) or the updated original (if TBA)
     */
    public function handle(Event $originalEvent, array $newEventData = [], ?string $reason = null): Event
    {
        return DB::transaction(function () use ($originalEvent, $newEventData, $reason) {
            // TBA mode: no new date provided, just mark as postponed
            if (empty($newEventData['start_datetime'])) {
                $originalEvent->update([
                    'status' => EventStatus::Postponed,
                    'reschedule_reason' => $reason,
                ]);

                return $originalEvent->fresh();
            }

            // Full reschedule: create new event with updated time
            $newEventData = array_merge(
                $originalEvent->only([
                    'title',
                    'description',
                    'location',
                    'event_link',
                    'ticket_url',
                    'ticket_price',
                    'organizer_id',
                    'visibility',
                    'event_type',
                    'distance_from_corvallis',
                ]),
                $newEventData
            );

            $newEvent = CreateEvent::run($newEventData);

            // Copy performers if they exist
            foreach ($originalEvent->performers as $performer) {
                AddEventPerformer::run($newEvent, $performer, [
                    'order' => $performer->pivot->order,
                    'set_length' => $performer->pivot->set_length,
                ]);
            }

            // Copy tags
            if ($originalEvent->tags->isNotEmpty()) {
                $newEvent->syncTagsWithType($originalEvent->tags->pluck('name')->toArray(), 'genre');
            }

            // Copy poster if it exists
            if ($originalEvent->hasMedia('poster')) {
                $originalEvent->getFirstMedia('poster')?->copy($newEvent, 'poster');
            }

            // Mark original event as rescheduled (sets status to Postponed, links to new event)
            $originalEvent->reschedule($newEvent, $reason);

            // Unpublish original event to prevent confusion
            $originalEvent->unpublish();

            return $newEvent;
        });
    }
}
