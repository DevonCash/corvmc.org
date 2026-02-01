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
                    'venue_id',
                    'event_link',
                    'ticket_url',
                    'ticket_price',
                    'organizer_id',
                    'visibility',
                    'event_type',
                    'distance_from_corvallis',
                    'ticketing_enabled',
                    'ticket_quantity',
                    'ticket_price_override',
                ]),
                // Preserve time offsets from original event for doors/end if not explicitly set
                $this->preserveTimeOffsets($originalEvent, $newEventData),
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

            // Link original event to new one
            // Keep cancelled events as cancelled; mark others as postponed
            $newStatus = $originalEvent->status === EventStatus::Cancelled
                ? EventStatus::Cancelled
                : EventStatus::Postponed;

            $originalEvent->update([
                'status' => $newStatus,
                'rescheduled_to_id' => $newEvent->id,
                'reschedule_reason' => $reason,
            ]);

            // Unpublish original event to prevent confusion
            $originalEvent->unpublish();

            return $newEvent;
        });
    }

    /**
     * Preserve time offsets from the original event for doors and end times.
     *
     * If the original event had doors open 30 minutes before start, the new event
     * will also have doors open 30 minutes before its new start time.
     */
    private function preserveTimeOffsets(Event $originalEvent, array $newEventData): array
    {
        $preserved = [];
        $newStart = $newEventData['start_datetime'];

        // Preserve doors time offset if original had doors_datetime
        if ($originalEvent->doors_datetime && ! isset($newEventData['doors_datetime'])) {
            $doorsOffset = $originalEvent->start_datetime->diffInMinutes($originalEvent->doors_datetime, false);
            $preserved['doors_datetime'] = $newStart->copy()->addMinutes($doorsOffset);
        }

        // Preserve end time offset if original had end_datetime and new doesn't
        if ($originalEvent->end_datetime && ! isset($newEventData['end_datetime'])) {
            $endOffset = $originalEvent->start_datetime->diffInMinutes($originalEvent->end_datetime);
            $preserved['end_datetime'] = $newStart->copy()->addMinutes($endOffset);
        }

        return $preserved;
    }
}
