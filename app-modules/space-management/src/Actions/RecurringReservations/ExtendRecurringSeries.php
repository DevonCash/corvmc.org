<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use Carbon\Carbon;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtendRecurringSeries
{
    use AsAction;

    /**
     * Extend series end date and generate new instances.
     */
    public function handle(RecurringSeries $series, Carbon $newEndDate): void
    {
        $series->update(['series_end_date' => $newEndDate]);

        // Generate new instances
        GenerateRecurringInstances::run($series);
    }
}
