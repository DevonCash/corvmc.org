<?php

namespace App\Actions\Reservations;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAllConflicts
{
    use AsAction;

    /**
     * Get all conflicts (both reservations and productions) for a time slot.
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return [
            'reservations' => GetConflictingReservations::run($startTime, $endTime, $excludeReservationId),
            'productions' => GetConflictingProductions::run($startTime, $endTime),
        ];
    }
}
