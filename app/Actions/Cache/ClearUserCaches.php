<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearUserCaches
{
    use AsAction;

    /**
     * Clear all user-related caches for a specific user.
     */
    public function handle(int $userId): void
    {
        Cache::forget("user.{$userId}.is_sustaining");
        Cache::forget("user.{$userId}.free_hours." . now()->format('Y-m'));
        Cache::forget("user_stats.{$userId}");
        Cache::forget("user_activity.{$userId}");

        // Clear global caches that might include this user
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
    }
}
