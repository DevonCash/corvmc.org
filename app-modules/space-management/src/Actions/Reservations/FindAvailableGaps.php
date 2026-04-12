<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

/**
 * @deprecated Use ReservationService::findAvailableGaps() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class FindAvailableGaps
{
    /**
     * @deprecated Use ReservationService::findAvailableGaps() instead
     */
    public function handle(Carbon $date, int $minimumDurationMinutes = 60): array
    {
        return app(ReservationService::class)->findAvailableGaps($date, $minimumDurationMinutes);
    }
}
