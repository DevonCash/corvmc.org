<?php

namespace App\Data\Subscription;

use Brick\Money\Money;
use Spatie\LaravelData\Data;

class SubscriptionStatsData extends Data
{
    public function __construct(
        public int $total_users,
        public int $sustaining_members,
        public int $total_free_hours_allocated,
        public Money $mrr_base,
        public Money $mrr_total,
        public Money $average_mrr,
        public Money $median_contribution,
        public int $active_subscriptions_count,
        public int $new_members_this_month,
        public int $subscription_net_change_last_month,
    ) {}
}
