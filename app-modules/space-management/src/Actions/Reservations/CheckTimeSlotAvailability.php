<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckTimeSlotAvailability
{
    use AsAction;

    /**
     * Check if a time slot is available (no conflicts with reservations or productions).
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): bool
    {
        // Invalid time period means slot is not available
        if ($endTime <= $startTime) {
            return false;
        }

        $conflicts = GetAllConflicts::run($startTime, $endTime, $excludeReservationId);

        return $conflicts['reservations']->isEmpty() && $conflicts['productions']->isEmpty();
    }
}
