<?php

namespace App\Actions\Subscriptions;

use App\Data\Subscription\SubscriptionStatsData;
use App\Models\User;
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

            return new SubscriptionStatsData(
                total_users: $totalUsers,
                sustaining_members: $sustainingMembers,
                total_free_hours_allocated: $totalAllocatedHours,
            );
        });
    }
}
