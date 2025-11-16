<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringSeries;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUpcomingRecurringInstances
{
    use AsAction;

    /**
     * Get upcoming instances for a series.
     */
    public function handle(RecurringSeries $series, int $limit = 10): Collection
    {
        $modelClass = $series->recurable_type;

        return $modelClass::where('recurring_series_id', $series->id)
            ->where('instance_date', '>=', now()->toDateString())
            ->orderBy('instance_date')
            ->limit($limit)
            ->get();
    }
}
