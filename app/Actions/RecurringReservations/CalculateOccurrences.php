<?php

namespace App\Actions\RecurringReservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use RRule\RRule;

class CalculateOccurrences
{
    use AsAction;

    /**
     * Calculate occurrence dates from recurrence rule using php-rrule library.
     *
     * @return Carbon[] Array of Carbon instances representing occurrence dates
     */
    public function handle(string $ruleString, Carbon $start, Carbon $end): array
    {
        // Parse RRULE using rlanvin/php-rrule
        $rrule = new RRule($ruleString, $start->toDateTimeString());

        $occurrences = [];
        foreach ($rrule as $occurrence) {
            $carbonOccurrence = Carbon::instance($occurrence);

            if ($carbonOccurrence->gt($end)) {
                break;
            }

            if ($carbonOccurrence->gte($start)) {
                $occurrences[] = $carbonOccurrence->copy();
            }
        }

        return $occurrences;
    }
}
