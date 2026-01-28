<?php

namespace CorvMC\Support\Actions;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use RRule\RRule;

/**
 * Calculate occurrence dates from an RRULE recurrence pattern.
 *
 * Uses the rlanvin/php-rrule library to parse and expand recurrence rules.
 */
class CalculateOccurrences
{
    use AsAction;

    /**
     * Calculate occurrence dates from recurrence rule.
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
