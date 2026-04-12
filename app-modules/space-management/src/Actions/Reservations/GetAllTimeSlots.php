<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ReservationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReservationService::getAllTimeSlots() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class GetAllTimeSlots
{
    use AsAction;

    public const MINUTES_PER_BLOCK = 30; // 30-minute intervals

    /**
     * @deprecated Use ReservationService::getAllTimeSlots() instead
     */
    public function handle(): array
    {
        return app(ReservationService::class)->getAllTimeSlots();
    }
}
