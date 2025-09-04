<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
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
        Cache::tags(['member_directory', 'tags'])->flush();
    }
}