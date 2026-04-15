<?php

namespace App\Services;

use App\Models\EventReservation;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\Events\Models\Event;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing event and space reservation synchronization.
 * 
 * This service handles the coordination between events and practice
 * space reservations, including conflict detection and resolution.
 */
class EventSyncService
{
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
    public function syncSpaceReservation(
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
                'conflicts' => ['reservations' => collect(), 'closures' => collect()],
                'reservation' => $existingReservation,
            ];
        }

        // Check for conflicts (excluding this event's own reservation if it exists)
        $conflicts = ReservationService::getConflicts(
            $reservedAt, 
            $reservedUntil, 
            excludeId: $existingReservation?->id,
            includeBuffer: false,
            includeClosures: true
        );

        $hasConflicts = $conflicts['reservations']->isNotEmpty()
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

    /**
     * Check for space conflicts without creating an event.
     *
     * @param  Carbon  $startTime  Event start time
     * @param  Carbon  $endTime  Event end time
     * @param  int|null  $setupMinutes  Minutes before event for setup (null uses default)
     * @param  int|null  $teardownMinutes  Minutes after event for teardown (null uses default)
     * @param  int|null  $excludeReservationId  Reservation ID to exclude from conflict check
     * @return array{status: string, event_conflicts: array, setup_conflicts: array, all_conflicts: array}
     */
    public function checkConflicts(
        Carbon $startTime,
        Carbon $endTime,
        ?int $setupMinutes = null,
        ?int $teardownMinutes = null,
        ?int $excludeReservationId = null
    ): array {
        $settings = app(ReservationSettings::class);
        $setupMinutes ??= $settings->default_event_setup_minutes;
        $teardownMinutes ??= $settings->default_event_teardown_minutes;

        $reservedAt = $startTime->copy()->subMinutes($setupMinutes);
        $reservedUntil = $endTime->copy()->addMinutes($teardownMinutes);

        // Get all conflicts for full period (setup + event + teardown)
        $excludeReservation = $excludeReservationId ? \CorvMC\SpaceManagement\Models\Reservation::find($excludeReservationId) : null;
        $allConflicts = ReservationService::getConflicts(
            $reservedAt, 
            $reservedUntil, 
            excludeId: $excludeReservationId,
            includeBuffer: false,
            includeClosures: true
        );

        // Also check conflicts for just the event time
        $eventConflicts = ReservationService::getConflicts(
            $startTime, 
            $endTime, 
            excludeId: $excludeReservationId,
            includeBuffer: false,
            includeClosures: true
        );

        // Determine if there are event-time conflicts
        $hasEventConflicts = $eventConflicts['reservations']->isNotEmpty()
            || $eventConflicts['closures']->isNotEmpty();

        // Determine if there are any conflicts (including setup/teardown)
        $hasAnyConflicts = $allConflicts['reservations']->isNotEmpty()
            || $allConflicts['closures']->isNotEmpty();

        // Calculate setup-only conflicts (conflicts that only affect setup/teardown, not event time)
        $setupConflicts = $this->calculateSetupOnlyConflicts($allConflicts, $eventConflicts);

        $hasSetupOnlyConflicts = ! $hasEventConflicts && $hasAnyConflicts;

        return [
            'status' => match (true) {
                $hasEventConflicts => 'event_conflict',
                $hasSetupOnlyConflicts => 'setup_conflict',
                default => 'available',
            },
            'event_conflicts' => $eventConflicts,
            'setup_conflicts' => $setupConflicts,
            'all_conflicts' => $allConflicts,
            'setup_minutes' => $setupMinutes,
            'teardown_minutes' => $teardownMinutes,
        ];
    }

    /**
     * Calculate conflicts that only affect setup/teardown periods, not the event itself.
     */
    private function calculateSetupOnlyConflicts(array $allConflicts, array $eventConflicts): array
    {
        return [
            'reservations' => $allConflicts['reservations']->diff($eventConflicts['reservations']),
            'closures' => $allConflicts['closures']->diff($eventConflicts['closures']),
        ];
    }

    /**
     * Remove the space reservation for an event.
     */
    public function removeSpaceReservation(Event $event): bool
    {
        $reservation = $event->spaceReservation;
        
        if (!$reservation) {
            return false;
        }

        return $reservation->delete();
    }

    /**
     * Check if an event has space conflicts.
     */
    public function hasConflicts(Event $event): bool
    {
        if (!$event->start_datetime) {
            return false;
        }

        $endTime = $event->end_datetime ?? $event->start_datetime->copy()->addHours(3);
        $result = $this->checkConflicts(
            $event->start_datetime,
            $endTime,
            excludeReservationId: $event->spaceReservation?->id
        );

        return $result['status'] !== 'available';
    }

    /**
     * Sync space reservations for multiple events.
     * 
     * Useful for batch operations or when importing events.
     */
    public function syncMultipleEvents(array $eventIds, bool $force = false): array
    {
        $results = [
            'synced' => 0,
            'conflicts' => 0,
            'errors' => 0,
            'details' => [],
        ];

        $events = Event::whereIn('id', $eventIds)->get();

        foreach ($events as $event) {
            try {
                $result = $this->syncSpaceReservation($event, force: $force);
                
                if ($result['success']) {
                    $results['synced']++;
                } else {
                    $results['conflicts']++;
                }

                $results['details'][$event->id] = $result;
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$event->id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get upcoming event conflicts for a date range.
     */
    public function getUpcomingConflicts(Carbon $from, Carbon $to): array
    {
        $events = Event::whereBetween('start_datetime', [$from, $to])
            ->with('spaceReservation')
            ->get();

        $conflicts = [];

        foreach ($events as $event) {
            if ($this->hasConflicts($event)) {
                $conflicts[] = [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'start_time' => $event->start_datetime,
                    'end_time' => $event->end_datetime,
                    'has_reservation' => $event->spaceReservation !== null,
                ];
            }
        }

        return $conflicts;
    }
}