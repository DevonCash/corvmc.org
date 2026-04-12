<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * @deprecated Use ReservationService::getAvailableTimeSlots() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class GetAvailableTimeSlots
{
    /**
     * @deprecated Use ReservationService::getAvailableTimeSlots() instead
     */
    public function handle(Carbon $date, int $durationHours = 1): array
    {
        return app(ReservationService::class)->getAvailableTimeSlots($date, $durationHours);
    }
}
