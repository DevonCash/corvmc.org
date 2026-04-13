<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\Finance\Facades\MemberBenefitService;
use CorvMC\SpaceManagement\Data\ReservationUsageData;
use App\Models\User;
use Carbon\Carbon;

class GetUserUsageForMonth
{
    /**
     * Get user's reservation usage for a specific month.
     *
     * Returns a ReservationUsageData object with:
     * - Month in Y-m format
     * - Total reservations count
     * - Total hours used
     * - Free hours used
     * - Total cost paid
     * - Allocated free hours for the month
     */
    public function handle(User $user, Carbon $month): ReservationUsageData
    {
        $reservations = $user->rehearsals()
            ->whereMonth('reserved_at', $month->month)
            ->whereYear('reserved_at', $month->year)
            ->where('free_hours_used', '>', 0)
            ->get();

        $totalFreeHours = $reservations->sum('free_hours_used');
        $totalHours = $reservations->sum('hours_used');
        $totalPaid = $reservations->sum('cost');

        $allocatedFreeHours = MemberBenefitService::getUserMonthlyFreeHours($user);

        return new ReservationUsageData(
            month: $month->format('Y-m'),
            total_reservations: $reservations->count(),
            total_hours: $totalHours,
            free_hours_used: $totalFreeHours,
            total_cost: $totalPaid,
            allocated_free_hours: $allocatedFreeHours,
        );
    }
}
