<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringReservation;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtendRecurringSeries
{
    use AsAction;

    /**
     * Extend series end date and generate new instances.
     */
    public function handle(RecurringReservation $series, Carbon $newEndDate): void
    {
        $series->update(['series_end_date' => $newEndDate]);

        // Generate new instances
        GenerateRecurringInstances::run($series);
    }
}
