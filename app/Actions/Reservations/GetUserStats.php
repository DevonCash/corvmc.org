<?php

namespace App\Actions\Reservations;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserStats
{
    use AsAction;

    /**
     * Get user's reservation statistics.
     *
     * Returns comprehensive statistics including:
     * - Total reservations count
     * - This month/year reservation counts
     * - This month/year hours used
     * - Free hours used this month
     * - Remaining free hours
     * - Total amount spent
     * - Sustaining member status
     */
    public function handle(User $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        return [
            'total_reservations' => $user->reservations()->count(),
            'this_month_reservations' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->count(),
            'this_year_hours' => $user->reservations()->where('reserved_at', '>=', $thisYear)->sum('hours_used'),
            'this_month_hours' => $user->reservations()->where('reserved_at', '>=', $thisMonth)->sum('hours_used'),
            'free_hours_used' => $user->getUsedFreeHoursThisMonth(),
            'remaining_free_hours' => $user->getRemainingFreeHours(),
            'total_spent' => $user->reservations()->sum('cost'),
            'is_sustaining_member' => $user->isSustainingMember(),
        ];
    }
}
