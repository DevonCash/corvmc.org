<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearReservationCaches
{
    use AsAction;

    /**
     * Clear all reservation-related caches for a specific date.
     */
    public function handle(string $date): void
    {
        Cache::forget("reservations.conflicts.{$date}");
    }
}
