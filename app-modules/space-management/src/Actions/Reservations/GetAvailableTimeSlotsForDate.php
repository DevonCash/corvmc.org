<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ConflictData;
use CorvMC\SpaceManagement\Services\ReservationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReservationService::getAvailableTimeSlotsForDate() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class GetAvailableTimeSlotsForDate
{
    use AsAction;

    /**
     * @deprecated Use ReservationService::getAvailableTimeSlotsForDate() instead
     */
    public function handle(Carbon $date, ?ConflictData $conflicts = null): array
    {
        return app(ReservationService::class)->getAvailableTimeSlotsForDate($date, $conflicts);
    }
}
