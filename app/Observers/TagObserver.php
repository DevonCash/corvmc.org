<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Tags\Tag;

class TagObserver
{
    /**
     * Handle the Tag "created" event.
     */
    public function created(Tag $tag): void
    {
        $this->clearTagCaches();
    }

    /**
     * Handle the Tag "updated" event.
     */
    public function updated(Tag $tag): void
    {
        $this->clearTagCaches();
    }

    /**
     * Handle the Tag "deleted" event.
     */
    public function deleted(Tag $tag): void
    {
        $this->clearTagCaches();
    }

    /**
     * Clear all tag-related caches.
     */
    private function clearTagCaches(): void
    {
        // Clear member directory filter caches using cache tags
        // Only use tags if the cache driver supports it (Redis, Memcached)
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
