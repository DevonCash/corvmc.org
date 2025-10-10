<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringReservation;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUpcomingRecurringInstances
{
    use AsAction;

    /**
     * Get upcoming instances for a series.
     */
    public function handle(RecurringReservation $series, int $limit = 10): Collection
    {
        return Reservation::where('recurring_reservation_id', $series->id)
            ->where('instance_date', '>=', now()->toDateString())
            ->orderBy('instance_date')
            ->limit($limit)
            ->get();
    }
}
