<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Data\Subscription\SubscriptionStatsData;
use CorvMC\Finance\Models\Subscription;
use CorvMC\Membership\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
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
                ->sum(fn ($user) => \App\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($user));

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

            // Calculate MRR metrics
            $mrrBaseInCents = $activeSubscriptions->sum(fn ($subscription) => $subscription->base_amount?->getMinorAmount()->toInt() ?? 0);
            $mrrTotalInCents = $activeSubscriptions->sum(fn ($subscription) => $subscription->total_amount?->getMinorAmount()->toInt() ?? 0);

            $mrrBase = Money::ofMinor($mrrBaseInCents, 'USD');
            $mrrTotal = Money::ofMinor($mrrTotalInCents, 'USD');

            // Calculate average MRR per active subscription
            $averageMrr = $activeSubscriptionsCount > 0
                ? Money::ofMinor((int) round($mrrTotalInCents / $activeSubscriptionsCount), 'USD')
                : Money::zero('USD');

            // Calculate median contribution
            $medianContribution = $this->calculateMedianContribution($activeSubscriptions);

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
     * Calculate the median contribution amount.
     */
    private function calculateMedianContribution($subscriptions): Money
    {
        if ($subscriptions->isEmpty()) {
            return Money::zero('USD');
        }

        // Get all total amounts in cents
        $amounts = $subscriptions
            ->map(fn ($subscription) => $subscription->total_amount?->getMinorAmount()->toInt() ?? 0)
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
