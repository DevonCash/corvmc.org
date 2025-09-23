<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class MemberBenefitsService
{
    const SUSTAINING_MEMBER_THRESHOLD = 10.00;
    const FREE_HOURS_PER_MONTH = 4;
    const HOURS_PER_DOLLAR_AMOUNT = 1; // Number of hours granted
    const DOLLAR_AMOUNT_FOR_HOURS = 5; // Per this dollar amount

    /**
     * Calculate free hours based on contribution amount.
     */
    public function calculateFreeHours(float $contributionAmount): int
    {
        return intval(floor($contributionAmount / self::DOLLAR_AMOUNT_FOR_HOURS) * self::HOURS_PER_DOLLAR_AMOUNT);
    }

    /**
     * Get the user's monthly free hours based on their subscription amount.
     */
    public function getUserMonthlyFreeHours(User $user): int
    {
        if (!$this->isSustainingMember($user)) {
            return 0;
        }

        // Use facade to avoid circular dependency
        $displayInfo = \App\Facades\UserSubscriptionService::getSubscriptionDisplayInfo($user);
        if ($displayInfo['has_subscription']) {
            return $this->calculateFreeHours($displayInfo['amount']);
        }

        // Fallback to legacy constant for role-based members without subscriptions
        return self::FREE_HOURS_PER_MONTH;
    }

    /**
     * Check if a user qualifies as a sustaining member.
     */
    public function isSustainingMember(User $user): bool
    {
        return $user->hasRole('sustaining member');
    }

    /**
     * Get used free hours for user in current month.
     */
    public function getUsedFreeHoursThisMonth(User $user): float
    {
        return Cache::remember("user.{$user->id}.free_hours." . now()->format('Y-m'), 1800, function () use ($user) {
            return $user->reservations()
                ->whereMonth('reserved_at', now()->month)
                ->whereYear('reserved_at', now()->year)
                ->sum('free_hours_used') ?? 0;
        });
    }

    /**
     * Get remaining free hours for sustaining members this month.
     */
    public function getRemainingFreeHours(User $user): float
    {
        if (!$this->isSustainingMember($user)) {
            return 0;
        }

        $allocatedHours = $this->getUserMonthlyFreeHours($user);
        $usedHours = $this->getUsedFreeHoursThisMonth($user);

        return max(0, $allocatedHours - $usedHours);
    }

    /**
     * Get member tier based on subscription amount.
     */
    public function getCurrentTier(User $user): ?string
    {
        $displayInfo = \App\Facades\UserSubscriptionService::getSubscriptionDisplayInfo($user);

        if (!$displayInfo['has_subscription']) {
            return null;
        }

        $amount = $displayInfo['amount'];

        return match (true) {
            $amount >= 45 && $amount <= 55 => 'suggested_50',
            $amount >= 20 && $amount <= 30 => 'suggested_25',
            $amount >= 8 && $amount <= 12 => 'suggested_10',
            default => 'custom'
        };
    }

    /**
     * Check if user qualifies for sustaining member benefits based on amount.
     */
    public function qualifiesForSustainingBenefits(float $amount): bool
    {
        return $amount >= self::SUSTAINING_MEMBER_THRESHOLD;
    }
}