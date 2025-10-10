<?php

namespace App\Actions\Reservations;

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
        // If we can't create a valid period, the slot is not available
        if (!\App\Facades\ReservationService::createPeriod($startTime, $endTime)) {
            return false;
        }

        $conflicts = GetAllConflicts::run($startTime, $endTime, $excludeReservationId);

        return $conflicts['reservations']->isEmpty() && $conflicts['productions']->isEmpty();
    }
}
