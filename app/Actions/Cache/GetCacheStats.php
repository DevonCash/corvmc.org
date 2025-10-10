<?php

namespace App\Actions\Cache;

use Lorisleiva\Actions\Concerns\AsAction;

class GetCacheStats
{
    use AsAction;

    /**
     * Get cache statistics for monitoring.
     */
    public function handle(): array
    {
        // This would require Redis to get detailed stats
        // For now, return basic info
        return [
            'cache_driver' => config('cache.default'),
            'redis_connection' => config('cache.stores.redis.connection'),
            'timestamp' => now(),
        ];
    }
}
