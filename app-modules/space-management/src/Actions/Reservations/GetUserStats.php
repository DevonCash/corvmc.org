<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\User;
use CorvMC\SpaceManagement\Services\ReservationService;

/**
 * @deprecated Use ReservationService::getUserStats() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class GetUserStats
{
    /**
     * @deprecated Use ReservationService::getUserStats() instead
     */
    public function handle(User $user): array
    {
        return app(ReservationService::class)->getUserStats($user);
    }
}
