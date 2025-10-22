<?php

namespace App\Actions\MemberBenefits;

use App\Enums\CreditType;
use App\Models\Reservation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRemainingFreeHours
{
    use AsAction;

    /**
     * Get remaining free hours for sustaining members this month.
     *
     * Uses Credits System exclusively.
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation (deprecated, kept for compatibility)
     */
    public function handle(User $user, bool $fresh = false): float
    {
        if (!CheckIsSustainingMember::run($user)) {
            return 0;
        }

        // Use Credits System exclusively
        $balanceInBlocks = $user->getCreditBalance(CreditType::FreeHours);
        return Reservation::blocksToHours($balanceInBlocks);
    }

    /**
     * Get used free hours for user in current month.
     *
     * @param bool $fresh Deprecated, kept for compatibility
     */
    public function getUsedFreeHoursThisMonth(User $user, bool $fresh = false): float
    {
        if (!CheckIsSustainingMember::run($user)) {
            return 0;
        }

        // Sum all negative credit transactions (deductions) this month
        $usedBlocks = \App\Models\CreditTransaction::where('user_id', $user->id)
            ->where('credit_type', 'free_hours')
            ->where('amount', '<', 0)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        // Convert negative value to positive and blocks to hours
        return Reservation::blocksToHours(abs($usedBlocks));
    }
}
