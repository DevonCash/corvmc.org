<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Data\EventFormData;
use CorvMC\Events\Events\EventScheduling;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Notifications\EventCreatedNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateEvent
{
    use AsAction;

    /**
     * Create a new event.
     */
    public function handle(array $data): Event
    {
        $formData = EventFormData::from($data);

        $event = DB::transaction(function () use ($formData, $data) {
            // Get resolved model attributes from DTO
            $attributes = $formData->toModelAttributes();

            // Set defaults
            $attributes['status'] ??= 'scheduled';
            $attributes['visibility'] ??= 'public';

            // Validate required fields
            $startDatetime = $formData->getStartDatetime();
            if (! $startDatetime) {
                throw new \InvalidArgumentException('Event start time is required but was not provided.');
            }

            // Fire scheduling hook - listeners can throw SchedulingConflictException
            EventScheduling::dispatch(
                $attributes,
                $startDatetime,
                $formData->getEndDatetime(),
                $attributes['venue_id'] ?? null
            );

            $event = Event::create($attributes);

            // Set flags if provided
            $notaflof = $formData->getNotaflof();
            if ($notaflof !== null) {
                $event->setNotaflof($notaflof);
            }

            // Attach tags if provided
            $tags = $formData->getTags();
            if (! empty($tags)) {
                $event->attachTags($tags);
            }

            return $event;
        });

        // Notify organizer outside transaction - don't let email failures affect event creation
        if ($event->organizer) {
            try {
                $event->organizer->notify(new EventCreatedNotification($event));
            } catch (\Exception $e) {
                \Log::error('Failed to send event creation notification', [
                    'event_id' => $event->id,
                    'organizer_id' => $event->organizer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $event;
    }
}
