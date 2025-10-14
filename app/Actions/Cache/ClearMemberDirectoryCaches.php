<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearMemberDirectoryCaches
{
    use AsAction;

    /**
     * Clear all member directory filter caches.
     */
    public function handle(): void
    {
        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
                Cache::tags(['member_directory', 'tags'])->flush();
            }
        } catch (\BadMethodCallException $e) {
            // Cache driver doesn't support tagging, skip
            Log::warning('Cache driver does not support tagging, skipping cache clear.');
        }
    }
}
