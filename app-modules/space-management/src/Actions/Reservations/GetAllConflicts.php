<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Facades\ReservationService;

class GetAllConflicts
{
    /**
     * Get all conflicts (reservations, productions, and closures) for a time slot.
     */
    public function handle(Carbon $startTime, Carbon $endTime, ?int $excludeReservationId = null): array
    {
        return [
            'reservations' => ReservationService::getConflictingReservations($startTime, $endTime, $excludeReservationId),
            'productions' => app(GetConflictingProductions::class)->handle($startTime, $endTime, $excludeReservationId),
            'closures' => ReservationService::getConflictingClosures($startTime, $endTime),
        ];
    }
}
