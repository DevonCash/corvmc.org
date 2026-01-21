<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\Reservation;
use App\Settings\ReservationSettings;
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
     * Expands the requested period by the configured buffer time on both ends.
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

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

        // Filter cached results for the specific time range (with buffer) and exclusion
        $filteredReservations = $dayReservations->filter(function (Reservation $reservation) use ($bufferedStart, $bufferedEnd, $excludeReservationId) {
            if ($excludeReservationId && $reservation->id === $excludeReservationId) {
                return false;
            }

            return $reservation->reserved_until > $bufferedStart && $reservation->reserved_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping reservations
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredReservations;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredReservations->filter(function (Reservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }
}
