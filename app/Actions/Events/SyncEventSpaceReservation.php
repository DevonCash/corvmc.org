<?php

namespace App\Actions\Events;

use App\Models\EventReservation;
use App\Settings\ReservationSettings;
use CorvMC\Events\Models\Event;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
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
     * @param  Event  $event  The event to sync the space reservation for
     * @param  int|null  $setupMinutes  Minutes before event start to block for setup (null uses default)
     * @param  int|null  $teardownMinutes  Minutes after event end to block for teardown (null uses default)
     * @param  bool  $force  Force the reservation even if there are conflicts (admin override)
     * @return array{success: bool, conflicts: array, reservation: ?EventReservation}
     */
    public function handle(
        Event $event,
        ?int $setupMinutes = null,
        ?int $teardownMinutes = null,
        bool $force = false
    ): array {
        $settings = app(ReservationSettings::class);

        $setupMinutes ??= $settings->default_event_setup_minutes;
        $teardownMinutes ??= $settings->default_event_teardown_minutes;

        $reservedAt = $event->start_datetime->copy()->subMinutes($setupMinutes);
        $reservedUntil = $event->end_datetime?->copy()->addMinutes($teardownMinutes)
            ?? $event->start_datetime->copy()->addHours(3);

        // If reservation already exists with same times, no sync needed
        $existingReservation = $event->spaceReservation;
        if ($existingReservation
            && $existingReservation->reserved_at->equalTo($reservedAt)
            && $existingReservation->reserved_until->equalTo($reservedUntil)
        ) {
            return [
                'success' => true,
                'conflicts' => ['reservations' => collect(), 'productions' => collect(), 'closures' => collect()],
                'reservation' => $existingReservation,
            ];
        }

        // Check for conflicts (excluding this event's own reservation if it exists)
        $excludeId = $existingReservation?->id;
        $conflicts = GetAllConflicts::run($reservedAt, $reservedUntil, $excludeId);

        $hasConflicts = $conflicts['reservations']->isNotEmpty()
            || $conflicts['productions']->isNotEmpty()
            || $conflicts['closures']->isNotEmpty();

        // If there are conflicts and we're not forcing, return without creating
        if ($hasConflicts && ! $force) {
            return [
                'success' => false,
                'conflicts' => $conflicts,
                'reservation' => null,
            ];
        }

        $reservation = $event->spaceReservation()->updateOrCreate(
            [],
            [
                'type' => (new EventReservation)->getMorphClass(),
                'reserved_at' => $reservedAt,
                'reserved_until' => $reservedUntil,
                'status' => ReservationStatus::Confirmed,
                'notes' => "Setup/breakdown for event: {$event->title}",
            ]
        );

        return [
            'success' => true,
            'conflicts' => $conflicts,
            'reservation' => $reservation,
        ];
    }
}
