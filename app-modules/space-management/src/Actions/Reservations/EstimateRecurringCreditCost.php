<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use App\Models\User;
use Carbon\Carbon;

/**
 * @deprecated Use ReservationService::estimateRecurringCreditCost() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class EstimateRecurringCreditCost
{
    /**
     * @deprecated Use ReservationService::estimateRecurringCreditCost() instead
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        return app(ReservationService::class)->estimateRecurringCreditCost($user, $startTime, $endTime, $recurrencePattern);
    }
}
