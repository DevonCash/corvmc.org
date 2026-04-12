<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::generateFutureRecurringInstances() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class GenerateFutureRecurringInstances
{
    use AsAction;

    /**
     * Generate future instances for all active reservation series.
     */
    public function handle(): void
    {
        $activeSeries = RecurringSeries::where('status', RecurringSeriesStatus::ACTIVE)
            ->where('recurable_type', 'rehearsal_reservation')
            ->where(function ($q) {
                $q->whereNull('series_end_date')
                    ->orWhere('series_end_date', '>', now());
            })
            ->get();

        $service = app(RecurringReservationService::class);
        
        foreach ($activeSeries as $series) {
            $service->generateFutureRecurringInstances($series);
        }
    }
}
