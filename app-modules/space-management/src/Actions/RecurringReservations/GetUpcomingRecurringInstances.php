<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Support\Collection;

/**
 * @deprecated Use RecurringReservationService::getUpcomingRecurringInstances() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class GetUpcomingRecurringInstances
{
    /**
     * Get upcoming instances for a series.
     */
    public function handle(RecurringSeries $series, int $limit = 10): Collection
    {
        return app(RecurringReservationService::class)->getUpcomingRecurringInstances($series, $limit);
    }
}
