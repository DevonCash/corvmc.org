<?php

namespace CorvMC\Finance\Services;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\SubscriptionService;
use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing member benefits and free hours calculations.
 * 
 * This service handles the calculation and allocation of member benefits,
 * particularly free practice hours based on subscription amounts.
 */
class MemberBenefitService
{
    /**
     * Number of hours granted per dollar amount contributed.
     */
    public const HOURS_PER_DOLLAR_AMOUNT = 1;
    
    /**
     * Dollar amount required for each hour of free time.
     */
    public const DOLLAR_AMOUNT_FOR_HOURS = 5;
    
    /**
     * Default free hours per month (fallback value).
     */
    public const FREE_HOURS_PER_MONTH = 4;

    /**
     * Calculate free hours based on contribution amount.
     *
     * Formula: 1 hour per $5 contributed
     * Example: $25/month = 5 hours, $50/month = 10 hours
     * 
     * @param float $contributionAmount The contribution amount in dollars
     * @return int The number of free hours earned
     */
    public function calculateFreeHours(float $contributionAmount): int
    {
        return intval(floor($contributionAmount / self::DOLLAR_AMOUNT_FOR_HOURS) * self::HOURS_PER_DOLLAR_AMOUNT);
    }

    /**
     * Get the user's monthly free hours based on their subscription amount.
     *
     * Returns 0 if not a sustaining member.
     * Uses peak billing amount if subscription exists, otherwise returns default.
     *
     * @param User $user The user to check
     * @param int|null $subscriptionAmountInCents Optional verified subscription amount in cents (from checkout metadata)
     * @return int The number of free hours for the month
     */
    public function getUserMonthlyFreeHours(User $user, ?int $subscriptionAmountInCents = null): int
    {
        if (!$user->isSustainingMember()) {
            return 0;
        }

        // If we have a verified subscription amount (e.g., from checkout redirect),
        // trust it over the DB (which may not have synced yet)
        // Convert from cents to dollars only for the calculation
        if ($subscriptionAmountInCents !== null) {
            return $this->calculateFreeHours($subscriptionAmountInCents / 100);
        }

        // Use subscription data if available
        $subscription = $user->subscription();
        if ($subscription?->active()) {
            try {
                // Get the maximum contribution amount for this billing period
                $peakAmount = SubscriptionService::getBillingPeriodPeakAmount($subscription);

                return $this->calculateFreeHours($peakAmount);
            } catch (\Exception $e) {
                Log::warning('Failed to get billing period peak amount for free hours calculation', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
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
     *
     * This should be called when a user becomes a sustaining member
     * or at the start of each billing period.
     *
     * @param User $user The user to allocate credits to
     * @param int|null $subscriptionAmountInCents Optional subscription amount in cents (from verified payment metadata)
     */
    public function allocateUserMonthlyCredits(User $user, ?int $subscriptionAmountInCents = null): void
    {
        if (!$user->isSustainingMember()) {
            return;
        }

        $hours = $this->getUserMonthlyFreeHours($user, $subscriptionAmountInCents);
        $blocks = Reservation::hoursToBlocks($hours);

        // Use CreditService to handle the allocation (handles reset logic)
        app(CreditService::class)->allocateMonthlyCredits($user, $blocks, CreditType::FreeHours);
    }
}