<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::skipRecurringInstance() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class SkipRecurringInstance
{
    use AsAction;

    /**
     * Skip a single instance without cancelling series.
     */
    public function handle(RecurringSeries $series, Carbon $date): bool
    {
        return app(RecurringReservationService::class)->skipRecurringInstance($series, $date);
    }
}
