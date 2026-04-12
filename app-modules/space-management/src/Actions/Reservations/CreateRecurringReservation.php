<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\ReservationService;

/**
 * @deprecated Use ReservationService::createRecurringReservation() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class CreateRecurringReservation
{
    /**
     * @deprecated Use ReservationService::createRecurringReservation() instead
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        return app(ReservationService::class)->createRecurringReservation($user, $startTime, $endTime, $recurrencePattern);
    }
}
