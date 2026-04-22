<?php

namespace CorvMC\SpaceManagement\Services;

use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class ReservationService
{
    /**
     * Unified conflict checking method.
     *
     * @param Carbon $startTime Start time to check
     * @param Carbon $endTime End time to check
     * @param int|null $excludeId Reservation ID to exclude
     * @param bool $includeBuffer Apply buffer time (default: true)
     * @param bool $includeClosures Check closures (default: true)
     * @return Collection<int, Reservation|SpaceClosure>
     */
    public function getConflicts(
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeId = null,
        bool $includeBuffer = true,
        bool $includeClosures = true,
    ): Collection {
        $bufferMinutes = $includeBuffer ? app(ReservationSettings::class)->buffer_minutes : 0;
        $bufferedStart = $startTime->copy()->subMinutes($bufferMinutes);
        $bufferedEnd = $endTime->copy()->addMinutes($bufferMinutes);

        // Get ALL reservations (includes RehearsalReservation and EventReservation via STI)
        $reservations = $this->getConflictingReservationsInternal($bufferedStart, $bufferedEnd, $excludeId);

        // Get closures if requested and merge them into the collection
        if ($includeClosures) {
            $reservations = $reservations->merge($this->getConflictingClosuresInternal($bufferedStart, $bufferedEnd));
        }

        // Return unified Collection containing both Reservation and SpaceClosure models
        return $reservations;
    }

    /**
     * Get available time slots for a given date considering all reservations.
     */
    public function getAvailableTimeSlots(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM

        // Get all reservations for the day once to optimize queries
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $conflicts = $this->getConflicts(
            $dayStart,
            $dayEnd,
            includeBuffer: false,
            includeClosures: true
        );

        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            $slotPeriod = Period::make($slotStart, $slotEnd, Precision::MINUTE());

            $hasReservationConflict = $conflicts->contains(function (Reservation $reservation) use ($slotPeriod) {
                return $reservation->overlapsWith($slotPeriod);
            });

            if (! $hasReservationConflict) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'duration' => $durationHours,
                    'period' => $slotPeriod,
                ];
            }
        }

        return $slots;
    }

    /**
     * Internal helper to get conflicting reservations.
     */
    private function getConflictingReservationsInternal(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): Collection
    {
        // Simple database query - let the DB do the rectangle select
        $query = Reservation::with('reservable')
            ->where('reserved_until', '>', $startTime)
            ->where('reserved_at', '<', $endTime)
            ->when($excludeReservationId, function ($q) use ($excludeReservationId) {
                $q->where('id', '!=', $excludeReservationId);
            });

        return $query->get();
    }


    /**
     * Internal helper to get conflicting closures.
     */
    private function getConflictingClosuresInternal(Carbon $startTime, Carbon $endTime): Collection
    {
        // Simple database query - let the DB do the rectangle select
        $query = SpaceClosure::query()
            ->where('ends_at', '>', $startTime)
            ->where('starts_at', '<', $endTime);

        return $query->get();
    }



    /**
     * Get valid end times for a specific date and start time, avoiding conflicts.
     */
    public function getValidEndTimesForDate(Carbon $date, string $startTime, ?Collection $conflicts = null): array
    {
        // Fetch conflicts once if not provided
        $conflicts ??= $this->getConflicts(
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
            includeBuffer: false,
            includeClosures: true
        );

        $slots = [];
        $start = $date->copy()->setTimeFromTimeString($startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(8); // MAX_RESERVATION_DURATION

        // Don't go past 10 PM
        $businessEnd = $date->copy()->setTime(22, 0);
        if ($latestEnd->greaterThan($businessEnd)) {
            $latestEnd = $businessEnd;
        }

        $current = $earliestEnd->copy();
        while ($current->lessThanOrEqualTo($latestEnd)) {
            $timeString = $current->format('H:i');

            // Check if this end time would cause conflicts
            $testPeriod = Period::make($start, $current, Precision::MINUTE());
            $hasConflicts = $conflicts->contains(function ($item) use ($testPeriod) {
                // Check if item has an overlapsWith method (both Reservation and SpaceClosure have this via HasTimePeriod trait)
                if (method_exists($item, 'overlapsWith')) {
                    return $item->overlapsWith($testPeriod);
                }
                return false;
            });

            if (! $hasConflicts) {
                $slots[$timeString] = $current->format('g:i A');
            }

            $current->addMinutes(30); // MINUTES_PER_BLOCK
        }

        return $slots;
    }
}
