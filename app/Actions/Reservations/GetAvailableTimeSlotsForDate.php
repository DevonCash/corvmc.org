<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAvailableTimeSlotsForDate
{
    use AsAction;

    /**
     * Get available time slots for a specific date, filtering out conflicted and past times.
     *
     * Returns simplified format of available start times (not full slot objects).
     * Tests each slot with 1 hour duration for availability.
     */
    public function handle(Carbon $date): array
    {
        $allSlots = GetAllTimeSlots::run();
        $availableSlots = [];

        foreach ($allSlots as $timeString => $label) {
            $testStart = $date->copy()->setTimeFromTimeString($timeString);
            $testEnd = $testStart->copy()->addHour(); // Test with 1 hour duration

            // Only check for conflicts and past times, not duration limits
            // since users might want shorter or longer reservations
            $hasConflicts = !CheckTimeSlotAvailability::run($testStart, $testEnd);
            $isPast = $testStart->isPast();

            // Only include slots that don't have conflicts and are in the future
            if (!$hasConflicts && !$isPast) {
                $availableSlots[$timeString] = $label;
            }
        }

        return $availableSlots;
    }
}
