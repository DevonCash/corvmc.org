<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class WarmUpCaches
{
    use AsAction;

    /**
     * Warm up commonly used caches.
     */
    public function handle(): void
    {
        // Warm up tag caches for member directory (only if cache supports tagging)
        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
                Cache::tags(['member_directory', 'tags'])->remember('member_directory.skills', 3600, function () {
                    return \Spatie\Tags\Tag::where('type', 'skill')->pluck('name', 'name')->toArray();
                });

                Cache::tags(['member_directory', 'tags'])->remember('member_directory.genres', 3600, function () {
                    return \Spatie\Tags\Tag::where('type', 'genre')->pluck('name', 'name')->toArray();
                });

                Cache::tags(['member_directory', 'tags'])->remember('member_directory.influences', 3600, function () {
                    return \Spatie\Tags\Tag::where('type', 'influence')->pluck('name', 'name')->toArray();
                });
            }
        } catch (\BadMethodCallException $e) {
            // Cache driver doesn't support tagging, skip
            Log::warning('Cache driver does not support tagging, skipping cache clear.');
        }

        // Warm up subscription stats
        \CorvMC\Finance\Actions\Subscriptions\GetSubscriptionStats::run();
        \CorvMC\Finance\Actions\Subscriptions\GetSustainingMembers::run();
    }
}
