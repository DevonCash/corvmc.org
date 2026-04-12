<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ReservationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReservationService::calculateReservationCost() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class CalculateReservationCost
{
    use AsAction;

    public const HOURLY_RATE = 15.00;

    /**
     * @deprecated Use ReservationService::calculateReservationCost() instead
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime): array
    {
        return app(ReservationService::class)->calculateReservationCost($user, $startTime, $endTime);
    }
}
