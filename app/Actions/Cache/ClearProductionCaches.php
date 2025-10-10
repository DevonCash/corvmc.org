<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearProductionCaches
{
    use AsAction;

    /**
     * Clear all production-related caches for a specific date.
     */
    public function handle(string $date): void
    {
        Cache::forget("productions.conflicts.{$date}");
        Cache::forget('upcoming_events');

        // Note: We can't easily clear all user-specific upcoming events caches
        // without scanning all keys, so they'll expire naturally
    }
}
