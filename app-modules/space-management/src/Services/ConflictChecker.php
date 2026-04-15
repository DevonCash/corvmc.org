<?php

namespace CorvMC\SpaceManagement\Services;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Contracts\ConflictCheckerInterface;

class ConflictChecker implements ConflictCheckerInterface
{
    public function checkConflicts(Carbon $start, Carbon $end, ?int $excludeReservationId = null): array
    {
        $excludeReservation = $excludeReservationId ? \CorvMC\SpaceManagement\Models\Reservation::find($excludeReservationId) : null;
        return ReservationService::getConflicts(
            $start,
            $end,
            excludeId: $excludeReservation?->id,
            includeBuffer: false,
            includeClosures: false
        );
    }
}
