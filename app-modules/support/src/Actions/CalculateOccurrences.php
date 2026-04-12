<?php

namespace CorvMC\Support\Actions;

use Carbon\Carbon;
use CorvMC\Support\Services\RecurringService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Calculate occurrence dates from an RRULE recurrence pattern.
 *
 * @deprecated Use RecurringService::calculateOccurrences() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringService directly.
 */
class CalculateOccurrences
{
    use AsAction;

    /**
     * Calculate occurrence dates from recurrence rule.
     *
     * @deprecated Use RecurringService::calculateOccurrences() instead
     * @return Carbon[] Array of Carbon instances representing occurrence dates
     */
    public function handle(string $ruleString, Carbon $start, Carbon $end): array
    {
        return app(RecurringService::class)->calculateOccurrences($ruleString, $start, $end);
    }
}
