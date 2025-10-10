<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearSubscriptionCaches
{
    use AsAction;

    /**
     * Clear all subscription-related caches.
     */
    public function handle(): void
    {
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
    }
}
