<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class GetConflictingReservations
{
    use AsAction;

    /**
     * Get potentially conflicting reservations for a time slot.
     * Uses a broader database query then filters with Period for precision.
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $cacheKey = 'reservations.conflicts.'.$startTime->format('Y-m-d');

        // Cache all day's reservations, then filter for specific conflicts
        $dayReservations = Cache::remember($cacheKey, 1800, function () use ($startTime) {
            $dayStart = $startTime->copy()->startOfDay();
            $dayEnd = $startTime->copy()->endOfDay();

            return Reservation::with('reservable')
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->get();
        });

        // Filter cached results for the specific time range and exclusion
        $filteredReservations = $dayReservations->filter(function (Reservation $reservation) use ($startTime, $endTime, $excludeReservationId) {
            if ($excludeReservationId && $reservation->id === $excludeReservationId) {
                return false;
            }

            return $reservation->reserved_until > $startTime && $reservation->reserved_at < $endTime;
        });

        // If invalid time period, return all potentially overlapping reservations
        if ($endTime <= $startTime) {
            return $filteredReservations;
        }

        // Use Period for precise overlap detection
        $requestedPeriod = Period::make($startTime, $endTime, Precision::MINUTE());

        return $filteredReservations->filter(function (Reservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }
}
