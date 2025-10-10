<?php

namespace App\Actions\Reservations;

use App\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateReservationCost
{
    use AsAction;

    public const HOURLY_RATE = 15.00;

    /**
     * Calculate the cost breakdown for a reservation.
     *
     * Returns an array with:
     * - total_hours: Total duration in hours
     * - free_hours: Hours covered by member benefits
     * - paid_hours: Hours requiring payment
     * - cost: Money object for total cost
     * - hourly_rate: Rate per hour
     * - is_sustaining_member: Whether user is a sustaining member
     * - remaining_free_hours: User's remaining free hours this month
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime): array
    {
        $hours = $startTime->diffInMinutes($endTime) / 60;

        // Use fresh calculation (bypass cache) for transaction safety during reservation creation
        $remainingFreeHours = $user->getRemainingFreeHours($fresh = true);

        $freeHours = $user->isSustainingMember() ? min($hours, $remainingFreeHours) : 0;
        $paidHours = max(0, $hours - $freeHours);

        $cost = Money::of(self::HOURLY_RATE, 'USD')->multipliedBy($paidHours);

        return [
            'total_hours' => $hours,
            'free_hours' => $freeHours,
            'paid_hours' => $paidHours,
            'cost' => $cost,
            'hourly_rate' => self::HOURLY_RATE,
            'is_sustaining_member' => $user->isSustainingMember(),
            'remaining_free_hours' => $remainingFreeHours,
        ];
    }

}
