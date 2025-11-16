<?php

namespace App\Data\Subscription;

use Spatie\LaravelData\Data;

class SubscriptionStatsData extends Data
{
    public function __construct(
        public int $total_users,
        public int $sustaining_members,
        public int $total_free_hours_allocated,
    ) {}
}
