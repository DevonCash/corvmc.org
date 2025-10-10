<?php

namespace App\Actions\Sponsors;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearSponsorCaches
{
    use AsAction;

    /**
     * Clear all sponsor caches
     */
    public function handle(): void
    {
        Cache::forget('sponsors.active.grouped');
        Cache::forget('sponsors.major');
        Cache::forget('sponsors.event_logo');
    }
}
