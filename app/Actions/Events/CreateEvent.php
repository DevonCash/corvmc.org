<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Notifications\EventCreatedNotification;
use Carbon\Carbon;
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
        $event = DB::transaction(function () use ($data) {
            // Convert location data if needed
            if (isset($data['at_cmc'])) {
                $data['location']['is_external'] = ! $data['at_cmc'];
                unset($data['at_cmc']);
            }

            $data['status'] ??= 'scheduled';
            $data['moderation_status'] ??= 'pending';

            // Combine virtual date/time fields into datetime fields for conflict checking
            // This handles both the new format (event_date + time_only) and old format (start_time directly)
            $startTime = $this->getStartTime($data);
            $endTime = $this->getEndTime($data);

            // Validate required fields
            if (! $startTime) {
                throw new \InvalidArgumentException('Event start time is required but was not provided.');
            }

            // Check for conflicts if this event uses the practice space
            if ($startTime && $endTime) {
                $isExternal = isset($data['location']['is_external']) ? $data['location']['is_external'] : false;
                if (! $isExternal) {
                    $conflicts = \App\Actions\Reservations\GetAllConflicts::run($startTime, $endTime);

                    if ($conflicts['reservations']->isNotEmpty()) {
                        throw new \InvalidArgumentException('Event conflicts with existing reservation');
                    }
                }
            }

            $event = Event::create($data);

            // Set flags if provided
            if (isset($data['notaflof'])) {
                $event->setNotaflof($data['notaflof']);
            }

            // Attach tags if provided
            if (! empty($data['tags'])) {
                $event->attachTags($data['tags']);
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

    /**
     * Extract or build the start datetime from the data array.
     * Handles both form format (event_date + start_time) and direct format (start_datetime).
     */
    protected function getStartTime(array $data): ?Carbon
    {
        // Form format: event_date + start_time
        if (isset($data['event_date']) && isset($data['start_time'])) {
            return Carbon::parse(
                "{$data['event_date']} {$data['start_time']}",
                config('app.timezone')
            );
        }

        // Direct format: start_datetime
        if (isset($data['start_datetime'])) {
            return $data['start_datetime'] instanceof Carbon
                ? $data['start_datetime']
                : Carbon::parse($data['start_datetime'], config('app.timezone'));
        }

        return null;
    }

    /**
     * Extract or build the end datetime from the data array.
     * Handles both form format (event_date + end_time) and direct format (end_datetime).
     */
    protected function getEndTime(array $data): ?Carbon
    {
        // Form format: event_date + end_time
        if (isset($data['event_date']) && isset($data['end_time'])) {
            return Carbon::parse(
                "{$data['event_date']} {$data['end_time']}",
                config('app.timezone')
            );
        }

        // Direct format: end_datetime
        if (isset($data['end_datetime'])) {
            return $data['end_datetime'] instanceof Carbon
                ? $data['end_datetime']
                : Carbon::parse($data['end_datetime'], config('app.timezone'));
        }

        return null;
    }
}
