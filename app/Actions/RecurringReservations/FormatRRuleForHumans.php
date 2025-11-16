<?php

namespace App\Actions\RecurringReservations;

use Lorisleiva\Actions\Concerns\AsAction;
use RRule\RRule;

class FormatRRuleForHumans
{
    use AsAction;

    /**
     * Format RRULE string into human-readable text.
     *
     * Returns the original rule string if parsing fails.
     */
    public function handle(string $ruleString): string
    {
        try {
            $rrule = new RRule($ruleString);

            return $rrule->humanReadable();
        } catch (\Exception $e) {
            return $ruleString;
        }
    }
}
