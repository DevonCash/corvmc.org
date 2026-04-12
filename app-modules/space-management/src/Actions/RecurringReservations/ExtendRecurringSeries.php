<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::extendRecurringSeries() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class ExtendRecurringSeries
{
    use AsAction;

    /**
     * Extend series end date and generate new instances.
     */
    public function handle(RecurringSeries $series, Carbon $newEndDate): RecurringSeries
    {
        return app(RecurringReservationService::class)->extendRecurringSeries($series, $newEndDate);
    }
}
