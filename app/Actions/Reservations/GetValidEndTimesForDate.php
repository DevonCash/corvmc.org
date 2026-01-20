<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetValidEndTimesForDate
{
    use AsAction;

    public const MINUTES_PER_BLOCK = 30;

    public const MAX_RESERVATION_DURATION = 8; // hours

    /**
     * Get valid end times for a specific date and start time, avoiding conflicts.
     *
     * Combines time validation with conflict checking to return only truly available end times.
     *
     * @param  ConflictData|null  $conflicts  Pre-fetched conflicts for in-memory filtering
     */
    public function handle(Carbon $date, string $startTime, ?ConflictData $conflicts = null): array
    {
        // Fetch conflicts once if not provided
        $conflicts ??= GetConflictsForDate::run($date);

        $slots = [];
        $start = $date->copy()->setTimeFromTimeString($startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(self::MAX_RESERVATION_DURATION);

        // Don't go past 10 PM
        $businessEnd = $date->copy()->setTime(22, 0);
        if ($latestEnd->greaterThan($businessEnd)) {
            $latestEnd = $businessEnd;
        }

        $current = $earliestEnd->copy();
        while ($current->lessThanOrEqualTo($latestEnd)) {
            $timeString = $current->format('H:i');

            // Check if this end time would cause conflicts
            $hasConflicts = $conflicts->hasConflict($start, $current);

            if (! $hasConflicts) {
                $slots[$timeString] = $current->format('g:i A');
            }

            $current->addMinutes(self::MINUTES_PER_BLOCK);
        }

        return $slots;
    }
}
