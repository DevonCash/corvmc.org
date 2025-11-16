<?php

namespace App\Actions\Reservations;

use App\Models\EventReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class GetConflictingProductions
{
    use AsAction;

    /**
     * Get event reservations that conflict with a time slot.
     * Note: This checks EventReservation models which are automatically created for events.
     */
    public function handle(Carbon $startTime, Carbon $endTime): Collection
    {
        $cacheKey = 'event-reservations.conflicts.'.$startTime->format('Y-m-d');

        // Cache all day's event reservations, then filter for specific conflicts
        $dayEventReservations = Cache::remember($cacheKey, 3600, function () use ($startTime) {
            $dayStart = $startTime->copy()->startOfDay();
            $dayEnd = $startTime->copy()->endOfDay();

            return EventReservation::query()
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->with('event')
                ->get();
        });

        // Filter cached results for the specific time range
        $filteredEventReservations = $dayEventReservations->filter(function (EventReservation $reservation) use ($startTime, $endTime) {
            return $reservation->reserved_until > $startTime && $reservation->reserved_at < $endTime;
        });

        // If invalid time period, return all potentially overlapping event reservations
        if ($endTime <= $startTime) {
            return $filteredEventReservations;
        }

        // Use Period for precise overlap detection
        $requestedPeriod = Period::make($startTime, $endTime, Precision::MINUTE());

        return $filteredEventReservations->filter(function (EventReservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }
}
