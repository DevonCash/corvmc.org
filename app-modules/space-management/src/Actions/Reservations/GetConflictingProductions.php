<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\EventReservation;
use App\Settings\ReservationSettings;
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
     * Expands the requested period by the configured buffer time on both ends.
     *
     * @param  int|null  $excludeReservationId  Reservation ID to exclude from conflicts (e.g., when updating an existing event's reservation)
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

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

        // Filter cached results for the specific time range (with buffer) and exclusion
        $filteredEventReservations = $dayEventReservations->filter(function (EventReservation $reservation) use ($bufferedStart, $bufferedEnd, $excludeReservationId) {
            if ($excludeReservationId && $reservation->id === $excludeReservationId) {
                return false;
            }

            return $reservation->reserved_until > $bufferedStart && $reservation->reserved_at < $bufferedEnd;
        });

        // If invalid time period, return all potentially overlapping event reservations
        if ($bufferedEnd <= $bufferedStart) {
            return $filteredEventReservations;
        }

        // Use Period for precise overlap detection with buffered times
        $requestedPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        return $filteredEventReservations->filter(function (EventReservation $reservation) use ($requestedPeriod) {
            return $reservation->overlapsWith($requestedPeriod);
        });
    }
}
