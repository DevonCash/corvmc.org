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
        return DB::transaction(function () use ($data) {
            // Convert location data if needed
            if (isset($data['at_cmc'])) {
                $data['location']['is_external'] = ! $data['at_cmc'];
                unset($data['at_cmc']);
            }

            $data['status'] ??= 'approved';

            // Check for conflicts if this event uses the practice space
            if (isset($data['start_time']) && isset($data['end_time'])) {
                $isExternal = isset($data['location']['is_external']) ? $data['location']['is_external'] : false;
                if (! $isExternal) {
                    // Handle both Carbon instances and strings from form
                    $startTime = $data['start_time'] instanceof Carbon
                        ? $data['start_time']
                        : Carbon::parse($data['start_time'], config('app.timezone'));
                    $endTime = $data['end_time'] instanceof Carbon
                        ? $data['end_time']
                        : Carbon::parse($data['end_time'], config('app.timezone'));

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

            // Notify organizer (if community event)
            if ($event->organizer) {
                $event->organizer->notify(new EventCreatedNotification($event));
            }

            return $event;
        });
    }
}
