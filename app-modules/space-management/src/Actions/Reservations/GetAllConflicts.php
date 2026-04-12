<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;

class GetAllConflicts
{
    /**
     * Get all conflicts (reservations, productions, and closures) for a time slot.
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return [
            'reservations' => GetConflictingReservations::run($startTime, $endTime, $excludeReservationId),
            'productions' => GetConflictingProductions::run($startTime, $endTime, $excludeReservationId),
            'closures' => GetConflictingClosures::run($startTime, $endTime),
        ];
    }
}
