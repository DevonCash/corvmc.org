<?php

namespace App\Actions\Cache;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearDateRangeCaches
{
    use AsAction;

    /**
     * Clear caches for a specific date range.
     */
    public function handle(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): void
    {
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            Cache::forget("reservations.conflicts.{$dateString}");
            Cache::forget("productions.conflicts.{$dateString}");
            $currentDate->addDay();
        }
    }
}
