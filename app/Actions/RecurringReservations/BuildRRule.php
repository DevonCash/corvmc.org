<?php

namespace App\Actions\RecurringReservations;

use Lorisleiva\Actions\Concerns\AsAction;

class BuildRRule
{
    use AsAction;

    /**
     * Build RRULE string from form inputs.
     *
     * Accepts array with keys:
     * - frequency: (required) 'WEEKLY', 'MONTHLY', etc.
     * - interval: (optional) How often to repeat (default 1)
     * - by_day: (optional) Array of day abbreviations for weekly (e.g., ['MO', 'WE', 'FR'])
     * - by_month_day: (optional) Day of month for monthly patterns
     * - by_set_pos: (optional) For "first Monday" type patterns
     */
    public function handle(array $data): string
    {
        $parts = [];

        // Frequency (required)
        $parts[] = 'FREQ=' . strtoupper($data['frequency']);

        // Interval
        if (isset($data['interval']) && $data['interval'] > 1) {
            $parts[] = 'INTERVAL=' . $data['interval'];
        }

        // By day (for weekly)
        if (isset($data['by_day']) && is_array($data['by_day']) && count($data['by_day']) > 0) {
            $parts[] = 'BYDAY=' . implode(',', $data['by_day']);
        }

        // By month day (for monthly)
        if (isset($data['by_month_day'])) {
            $parts[] = 'BYMONTHDAY=' . $data['by_month_day'];
        }

        // By set pos (for "first Monday" patterns)
        if (isset($data['by_set_pos'])) {
            $parts[] = 'BYSETPOS=' . $data['by_set_pos'];
        }

        return implode(';', $parts);
    }
}
