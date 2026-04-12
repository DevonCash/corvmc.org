<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\SpaceManagement\Services\RecurringReservationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::buildRRule() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringReservationService directly.
 */
class BuildRRule
{
    use AsAction;

    /**
     * @deprecated Use RecurringReservationService::buildRRule() instead
     */
    public function handle(array $data): string
    {
        return app(RecurringReservationService::class)->buildRRule($data);
    }
}
