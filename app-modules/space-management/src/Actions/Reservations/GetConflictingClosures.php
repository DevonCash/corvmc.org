<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class GetConflictingClosures
{
    use AsAction;

    /**
     * Get space closures that conflict with a time slot.
     * Uses a broader database query then filters with Period for precision.
     * Expands the requested period by the configured buffer time on both ends.
     */
    public function handle(Carbon $startTime, Carbon $endTime): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

        $dayStart = $startTime->copy()->startOfDay();
        $dayEnd = $startTime->copy()->endOfDay();

        // Skip cache in testing to ensure fresh data
        if (app()->environment('testing')) {
            $dayClosures = SpaceClosure::query()
                ->with('createdBy')
                ->where('ends_at', '>', $dayStart)
                ->where('starts_at', '<', $dayEnd)
                ->get();
        } else {
            $cacheKey = 'closures.conflicts.'.$startTime->format('Y-m-d');

            // Cache all day's closures, then filter for specific conflicts
            $dayClosures = Cache::remember($cacheKey, 1800, function () use ($dayStart, $dayEnd) {
                return SpaceClosure::query()
                    ->with('createdBy')
                    ->where('ends_at', '>', $dayStart)
                    ->where('starts_at', '<', $dayEnd)
                    ->get();
            });
        }

        // Filter cached results for the specific time range (with buffer)
        $filteredClosures = $dayClosures->filter(function (SpaceClosure $closure) use ($bufferedStart, $bufferedEnd) {
            return $closure->ends_at > $bufferedStart && $closure->starts_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping closures
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredClosures;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredClosures->filter(function (SpaceClosure $closure) use ($requestedPeriod) {
            return $closure->overlapsWithPeriod($requestedPeriod);
        });
    }
}
