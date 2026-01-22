<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Events\EventScheduling;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Notifications\EventCreatedNotification;
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
            $data['status'] ??= 'scheduled';
            $data['visibility'] ??= 'public';

            // Combine virtual date/time fields into datetime fields for conflict checking
            // This handles both the new format (event_date + time_only) and old format (start_time directly)
            $startTime = $this->getStartTime($data);
            $endTime = $this->getEndTime($data);
            $doorsTime = $this->getDoorsTime($data);

            // Validate required fields
            if (! $startTime) {
                throw new \InvalidArgumentException('Event start time is required but was not provided.');
            }

            // Convert time-only fields to full datetimes
            if ($doorsTime) {
                $data['doors_datetime'] = $doorsTime;
                unset($data['doors_time']);
            }

            // Handle end_time if provided as time-only field
            if ($endTime) {
                $data['end_datetime'] = $endTime;
                unset($data['end_time']);
            }

            // Fire scheduling hook - listeners can throw SchedulingConflictException
            EventScheduling::dispatch(
                $data,
                $startTime,
                $endTime,
                $data['venue_id'] ?? null
            );

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

        // Time-only format with start_datetime
        if (isset($data['end_time']) && isset($data['start_datetime'])) {
            $baseDate = $data['start_datetime'] instanceof Carbon
                ? $data['start_datetime']
                : Carbon::parse($data['start_datetime'], config('app.timezone'));

            return Carbon::parse(
                $baseDate->format('Y-m-d').' '.$data['end_time'],
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

    /**
     * Extract or build the doors datetime from the data array.
     * Handles time-only format by combining with start_datetime date.
     */
    protected function getDoorsTime(array $data): ?Carbon
    {
        // Time-only format with start_datetime
        if (isset($data['doors_time']) && isset($data['start_datetime'])) {
            $baseDate = $data['start_datetime'] instanceof Carbon
                ? $data['start_datetime']
                : Carbon::parse($data['start_datetime'], config('app.timezone'));

            return Carbon::parse(
                $baseDate->format('Y-m-d').' '.$data['doors_time'],
                config('app.timezone')
            );
        }

        // Direct format: doors_datetime
        if (isset($data['doors_datetime'])) {
            return $data['doors_datetime'] instanceof Carbon
                ? $data['doors_datetime']
                : Carbon::parse($data['doors_datetime'], config('app.timezone'));
        }

        return null;
    }
}
