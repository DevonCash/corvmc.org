<?php

namespace CorvMC\Finance\Actions\Payments;

use CorvMC\Finance\Services\FeeService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use FeeService::markReservationAsRefunded() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class MarkReservationAsRefunded
{
    use AsAction;

    /**
     * @deprecated Use FeeService::markReservationAsRefunded() instead
     */
    public function handle(RehearsalReservation $reservation, ?string $notes = null): void
    {
        app(FeeService::class)->markReservationAsRefunded($reservation, $notes);
    }
}
