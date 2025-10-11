<?php

namespace App\Actions\MemberBenefits;

use App\Actions\Credits\GetBalance;
use App\Actions\Credits\GetHoursFromBlocks;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRemainingFreeHours
{
    use AsAction;

    /**
     * Get remaining free hours for sustaining members this month.
     *
     * Tries Credits System first (new system), falls back to legacy calculation.
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation
     */
    public function handle(User $user, bool $fresh = false): float
    {
        if (!CheckIsSustainingMember::run($user)) {
            return 0;
        }

        // Try Credits System first (new system)
        $balanceInBlocks = GetBalance::run($user, 'free_hours');

        if ($balanceInBlocks > 0) {
            // User has credits allocated - use Credits System
            return GetHoursFromBlocks::run($balanceInBlocks);
        }

        // Fallback to legacy calculation for users not yet migrated
        $allocatedHours = GetUserMonthlyFreeHours::run($user);
        $usedHours = $this->getUsedFreeHoursThisMonth($user, $fresh);

        return max(0, $allocatedHours - $usedHours);
    }

    /**
     * Get used free hours for user in current month (legacy).
     *
     * @deprecated Use Credits System instead
     */
    public function getUsedFreeHoursThisMonth(User $user, bool $fresh = false): float
    {
        $cacheKey = "user.{$user->id}.free_hours." . now()->format('Y-m');

        // For fresh calculations (during reservation creation), bypass cache
        if ($fresh) {
            $value = $user->reservations()
                ->whereMonth('reserved_at', now()->month)
                ->whereYear('reserved_at', now()->year)
                ->sum('free_hours_used') ?? 0;

            // Update cache with fresh value
            Cache::put($cacheKey, $value, 1800);
            return $value;
        }

        // For display purposes, use cached value
        return Cache::remember($cacheKey, 1800, function () use ($user) {
            return $user->reservations()
                ->whereMonth('reserved_at', now()->month)
                ->whereYear('reserved_at', now()->year)
                ->sum('free_hours_used') ?? 0;
        });
    }
}
