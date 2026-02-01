<?php

namespace App\Actions\Events;

use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEventConflicts
{
    use AsAction;

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
    public function handle(
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
        $allConflicts = GetAllConflicts::run($reservedAt, $reservedUntil, $excludeReservationId);

        // Also check conflicts for just the event time
        $eventConflicts = GetAllConflicts::run($startTime, $endTime, $excludeReservationId);

        // Determine if there are event-time conflicts
        $hasEventConflicts = $eventConflicts['reservations']->isNotEmpty()
            || $eventConflicts['productions']->isNotEmpty()
            || $eventConflicts['closures']->isNotEmpty();

        // Determine if there are any conflicts (including setup/teardown)
        $hasAnyConflicts = $allConflicts['reservations']->isNotEmpty()
            || $allConflicts['productions']->isNotEmpty()
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
            'productions' => $allConflicts['productions']->diff($eventConflicts['productions']),
            'closures' => $allConflicts['closures']->diff($eventConflicts['closures']),
        ];
    }
}
