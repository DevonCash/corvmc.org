<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $subscription = \App\Facades\UserSubscriptionService::getActiveSubscription($user);
        if ($subscription) {
            try {
                // Get the maximum contribution amount for this billing period
                $peakAmount = \App\Facades\UserSubscriptionService::getBillingPeriodPeakAmount($subscription);
                return $this->calculateFreeHours($peakAmount);
            } catch (\Exception $e) {
                Log::warning('Failed to get billing period peak amount for free hours calculation', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ]);
                // Fallback to default for active subscription
                return self::FREE_HOURS_PER_MONTH;
            }
        }

        // Fallback to legacy constant for role-based members without subscriptions
        return self::FREE_HOURS_PER_MONTH;
    }

    /**
     * Allocate monthly credits to a sustaining member.
     * This should be called when a user becomes a sustaining member or at the start of each billing period.
     */
    public function allocateMonthlyCredits(User $user): void
    {
        if (!$this->isSustainingMember($user)) {
            return;
        }

        $hours = $this->getUserMonthlyFreeHours($user);
        $blocks = \App\Facades\ReservationService::hoursToBlocks($hours);

        // Use CreditService to allocate the credits (handles reset logic)
        \App\Facades\CreditService::allocateMonthlyCredits($user, $blocks, 'free_hours');
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
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation
     * @deprecated Use CreditService::getBalance() instead
     */
    public function getUsedFreeHoursThisMonth(User $user, bool $fresh = false): float
    {
        // Legacy method for backward compatibility
        // TODO: Remove after full migration to Credits System
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

    /**
     * Get remaining free hours for sustaining members this month.
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation
     */
    public function getRemainingFreeHours(User $user, bool $fresh = false): float
    {
        if (!$this->isSustainingMember($user)) {
            return 0;
        }

        // Try Credits System first (new system)
        $balanceInBlocks = \App\Facades\CreditService::getBalance($user, 'free_hours');

        if ($balanceInBlocks > 0) {
            // User has credits allocated - use Credits System
            return \App\Facades\ReservationService::blocksToHours($balanceInBlocks);
        }

        // Fallback to legacy calculation for users not yet migrated
        $allocatedHours = $this->getUserMonthlyFreeHours($user);
        $usedHours = $this->getUsedFreeHoursThisMonth($user, $fresh);

        return max(0, $allocatedHours - $usedHours);
    }

    /**
     * Get member tier based on subscription amount.
     */
    public function getCurrentTier(User $user): ?string
    {
        $subscription = \App\Facades\UserSubscriptionService::getActiveSubscription($user);

        if (!$subscription) {
            return null;
        }

        try {
            // Get the Stripe subscription object with pricing info
            $stripeSubscription = $subscription->asStripeSubscription();
            $firstItem = $stripeSubscription->items->data[0];
            $amount = $firstItem->price->unit_amount / 100; // Convert from cents to dollars
        } catch (\Exception $e) {
            Log::warning('Failed to get subscription amount for tier calculation', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return 'custom';
        }

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
