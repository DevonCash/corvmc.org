<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetValidEndTimes
{
    use AsAction;

    public const MINUTES_PER_BLOCK = 30;

    public const MAX_RESERVATION_DURATION = 8; // hours

    /**
     * Get valid end time options based on start time.
     *
     * Respects:
     * - Minimum 1 hour reservation
     * - Maximum 8 hours reservation
     * - Business hours (must end by 10 PM)
     *
     * Returns array format: ['10:00' => '10:00 AM', '10:30' => '10:30 AM', ...]
     */
    public function handle(string $startTime): array
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', $startTime);

        // Minimum 1 hour, maximum 8 hours
        $earliestEnd = $start->copy()->addHour();
        $latestEnd = $start->copy()->addHours(self::MAX_RESERVATION_DURATION);

        // Don't go past 10 PM
        $businessEnd = Carbon::createFromTime(22, 0);
        if ($latestEnd->greaterThan($businessEnd)) {
            $latestEnd = $businessEnd;
        }

        $current = $earliestEnd->copy();
        while ($current->lessThanOrEqualTo($latestEnd)) {
            $timeString = $current->format('H:i');
            $slots[$timeString] = $current->format('g:i A');
            $current->addMinutes(self::MINUTES_PER_BLOCK);
        }

        return $slots;
    }
}
