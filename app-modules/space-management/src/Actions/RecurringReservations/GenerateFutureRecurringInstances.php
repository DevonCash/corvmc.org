<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateFutureRecurringInstances
{
    use AsAction;

    /**
     * Generate future instances for all active reservation series.
     */
    public function handle(): void
    {
        $activeSeries = RecurringSeries::where('status', 'active')
            ->where('recurable_type', Reservation::class)
            ->where(function ($q) {
                $q->whereNull('series_end_date')
                    ->orWhere('series_end_date', '>', now());
            })
            ->get();

        foreach ($activeSeries as $series) {
            GenerateRecurringInstances::run($series);
        }
    }
}
