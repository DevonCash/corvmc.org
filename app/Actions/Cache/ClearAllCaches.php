<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearAllCaches
{
    use AsAction;

    /**
     * Clear all application caches (use with caution).
     */
    public function handle(): void
    {
        Cache::flush();
    }
}
