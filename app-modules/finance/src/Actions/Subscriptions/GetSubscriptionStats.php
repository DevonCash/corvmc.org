<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Finance\Data\SubscriptionStatsData;
use CorvMC\Finance\Models\Subscription;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSubscriptionStats
{
    use AsAction;

    /**
     * Get subscription statistics.
     */
    public function handle(): SubscriptionStatsData
    {
        return Cache::remember('subscription_stats', 1800, function () {
            $totalUsers = User::count();
            $sustainingMembers = GetSustainingMembers::run()->count();

            // Calculate total allocated hours based on actual subscription amounts
            $totalAllocatedHours = GetSustainingMembers::run()
                ->sum(fn ($user) => \CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($user));

            // Get all active subscriptions
            $activeSubscriptions = Subscription::query()->active()->get();
            $activeSubscriptionsCount = $activeSubscriptions->count();

            // Calculate new members this month
            $newMembersThisMonth = User::whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])->count();

            // Calculate subscription net change last month
            $lastMonthStart = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            $newSubscriptionsLastMonth = Subscription::whereBetween('created_at', [
                $lastMonthStart,
                $lastMonthEnd,
            ])->count();

            $cancelledSubscriptionsLastMonth = Subscription::whereBetween('ends_at', [
                $lastMonthStart,
                $lastMonthEnd,
            ])->whereNotNull('ends_at')->count();

            $subscriptionNetChange = $newSubscriptionsLastMonth - $cancelledSubscriptionsLastMonth;

            // Get subscription amounts from Stripe
            $subscriptionAmounts = $this->getSubscriptionAmountsFromStripe($activeSubscriptions);

            // Calculate MRR metrics from Stripe data
            $mrrTotalInCents = $subscriptionAmounts->sum('amount');
            $mrrTotal = Money::ofMinor($mrrTotalInCents, 'USD');

            // Base MRR (before Stripe fees - estimate ~2.9% + 30¢)
            $mrrBaseInCents = $subscriptionAmounts->sum('amount');
            $mrrBase = Money::ofMinor($mrrBaseInCents, 'USD');

            // Calculate average MRR per active subscription
            $averageMrr = $activeSubscriptionsCount > 0
                ? Money::ofMinor((int) round($mrrTotalInCents / $activeSubscriptionsCount), 'USD')
                : Money::zero('USD');

            // Calculate median contribution
            $medianContribution = $this->calculateMedianContribution($subscriptionAmounts);

            return new SubscriptionStatsData(
                total_users: $totalUsers,
                sustaining_members: $sustainingMembers,
                total_free_hours_allocated: $totalAllocatedHours,
                mrr_base: $mrrBase,
                mrr_total: $mrrTotal,
                average_mrr: $averageMrr,
                median_contribution: $medianContribution,
                active_subscriptions_count: $activeSubscriptionsCount,
                new_members_this_month: $newMembersThisMonth,
                subscription_net_change_last_month: $subscriptionNetChange,
            );
        });
    }

    /**
     * Get subscription amounts from Stripe.
     *
     * @return Collection<int, array{subscription_id: int, amount: int}>
     */
    private function getSubscriptionAmountsFromStripe(Collection $subscriptions): Collection
    {
        return $subscriptions->map(function (Subscription $subscription) {
            try {
                // Get the Stripe subscription object
                $stripeSubscription = $subscription->asStripeSubscription();

                if (! $stripeSubscription) {
                    return null;
                }

                // Get the total amount from subscription items
                // Amount is in cents for USD
                $amount = 0;
                foreach ($stripeSubscription->items->data as $item) {
                    if ($item->price->recurring) {
                        $amount += $item->price->unit_amount * ($item->quantity ?? 1);
                    }
                }

                return [
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                ];
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Stripe subscription', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter()->values();
    }

    /**
     * Calculate the median contribution amount.
     */
    private function calculateMedianContribution(Collection $subscriptionAmounts): Money
    {
        if ($subscriptionAmounts->isEmpty()) {
            return Money::zero('USD');
        }

        // Get all amounts in cents
        $amounts = $subscriptionAmounts
            ->pluck('amount')
            ->filter(fn ($amount) => $amount > 0)
            ->sort()
            ->values();

        if ($amounts->isEmpty()) {
            return Money::zero('USD');
        }

        $count = $amounts->count();
        $middle = (int) floor($count / 2);

        // If even number of items, average the two middle values
        if ($count % 2 === 0) {
            $medianInCents = (int) round(($amounts[$middle - 1] + $amounts[$middle]) / 2);
        } else {
            $medianInCents = $amounts[$middle];
        }

        return Money::ofMinor($medianInCents, 'USD');
    }
}
