<?php

namespace App\Actions\Reservations;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetConflictingProductions
{
    use AsAction;

    /**
     * Get productions that conflict with a time slot (only those using practice space).
     */
    public function handle(Carbon $startTime, Carbon $endTime): Collection
    {
        $requestedPeriod = \App\Facades\ReservationService::createPeriod($startTime, $endTime);
        $cacheKey = "productions.conflicts." . $startTime->format('Y-m-d');

        // Cache all day's productions, then filter for specific conflicts
        $dayProductions = Cache::remember($cacheKey, 3600, function () use ($startTime) {
            $dayStart = $startTime->copy()->startOfDay();
            $dayEnd = $startTime->copy()->endOfDay();

            return Production::query()
                ->where('end_time', '>', $dayStart)
                ->where('start_time', '<', $dayEnd)
                ->get()
                ->filter(function (Production $production) {
                    return $production->usesPracticeSpace();
                });
        });

        // Filter cached results for the specific time range
        $filteredProductions = $dayProductions->filter(function (Production $production) use ($startTime, $endTime) {
            return $production->end_time > $startTime && $production->start_time < $endTime;
        });

        // If we can't create a valid period, return all potentially overlapping productions
        if (!$requestedPeriod) {
            return $filteredProductions;
        }

        return $filteredProductions->filter(function (Production $production) use ($requestedPeriod) {
            return $production->overlapsWith($requestedPeriod);
        });
    }
}
