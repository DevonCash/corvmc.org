<?php

namespace App\Actions\Events;

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\EventReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncEventSpaceReservation
{
    use AsAction;

    /**
     * Create or update the space reservation for an event.
     *
     * This creates an EventReservation that blocks the practice space
     * for the event duration plus setup/breakdown time.
     *
     * @param Event $event The event to sync the space reservation for
     * @return void
     */
    public function handle(Event $event): void
    {
        // Calculate reservation times (2 hours before start, 1 hour after end)
        $reservedAt = $event->start_datetime->copy()->subHours(2);
        $reservedUntil = $event->end_datetime?->copy()->addHour()
            ?? $event->start_datetime->copy()->addHours(3);

        // Create or update the space reservation
        $event->spaceReservation()->updateOrCreate(
            [],
            [
                'type' => EventReservation::class,
                'reserved_at' => $reservedAt,
                'reserved_until' => $reservedUntil,
                'status' => ReservationStatus::Confirmed,
                'notes' => "Setup/breakdown for event: {$event->title}",
            ]
        );
    }
}
