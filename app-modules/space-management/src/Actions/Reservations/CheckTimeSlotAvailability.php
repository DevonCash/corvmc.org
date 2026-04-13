<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Facades\ReservationService;

class CheckTimeSlotAvailability
{
    /**
     * Check if a time slot is available (no conflicts with reservations, productions, or closures).
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        // Invalid time period means slot is not available
        if ($endTime <= $startTime) {
            return false;
        }

        $conflicts = ReservationService::checkForConflicts($startTime, $endTime, $excludeReservationId);

        return $conflicts['reservations']->isEmpty()
            && $conflicts['productions']->isEmpty()
            && $conflicts['closures']->isEmpty();
    }
}
