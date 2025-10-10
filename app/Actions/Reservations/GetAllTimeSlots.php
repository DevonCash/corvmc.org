<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAllTimeSlots
{
    use AsAction;

    public const MINUTES_PER_BLOCK = 30; // 30-minute intervals

    /**
     * Get all time slots for the practice space (30-minute intervals).
     *
     * Returns an array of time slots from 9 AM to 10 PM in 30-minute intervals.
     * Format: ['09:00' => '9:00 AM', '09:30' => '9:30 AM', ...]
     */
    public function handle(): array
    {
        $slots = [];

        // Practice space hours: 9 AM to 10 PM
        $start = Carbon::createFromTime(9, 0);
        $end = Carbon::createFromTime(22, 0);

        $current = $start->copy();
        while ($current->lessThanOrEqualTo($end)) {
            $timeString = $current->format('H:i');
            $slots[$timeString] = $current->format('g:i A');
            $current->addMinutes(self::MINUTES_PER_BLOCK);
        }

        return $slots;
    }
}
