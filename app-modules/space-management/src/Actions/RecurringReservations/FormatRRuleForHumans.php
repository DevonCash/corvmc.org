<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\SpaceManagement\Services\RecurringReservationService;

/**
 * @deprecated Use RecurringReservationService::formatRRuleForHumans() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringReservationService directly.
 */
class FormatRRuleForHumans
{
    /**
     * @deprecated Use RecurringReservationService::formatRRuleForHumans() instead
     */
    // TODO: Turn this into a cast
    public function handle(string $ruleString): string
    {
        return app(RecurringReservationService::class)->formatRRuleForHumans($ruleString);
    }
}
