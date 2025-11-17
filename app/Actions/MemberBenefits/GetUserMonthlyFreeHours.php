<?php

namespace App\Actions\MemberBenefits;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserMonthlyFreeHours
{
    use AsAction;

    public const FREE_HOURS_PER_MONTH = 4; // Default fallback

    /**
     * Get the user's monthly free hours based on their subscription amount.
     *
     * Returns 0 if not a sustaining member.
     * Uses peak billing amount if subscription exists, otherwise returns default.
     *
     * @param  User  $user  The user to check
     * @param  int|null  $subscriptionAmountInCents  Optional verified subscription amount in cents (from checkout metadata)
     */
    public function handle(User $user, ?int $subscriptionAmountInCents = null): int
    {
        if (! CheckIsSustainingMember::run($user)) {
            return 0;
        }

        // If we have a verified subscription amount (e.g., from checkout redirect),
        // trust it over the DB (which may not have synced yet)
        // Convert from cents to dollars only for the calculation
        if ($subscriptionAmountInCents !== null) {
            return CalculateFreeHours::run($subscriptionAmountInCents / 100);
        }

        // Use actions for subscription data
        $subscription = \App\Actions\Subscriptions\GetActiveSubscription::run($user);
        if ($subscription) {
            try {
                // Get the maximum contribution amount for this billing period
                $peakAmount = \App\Actions\Subscriptions\GetBillingPeriodPeakAmount::run($subscription);

                return CalculateFreeHours::run($peakAmount);
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
}
