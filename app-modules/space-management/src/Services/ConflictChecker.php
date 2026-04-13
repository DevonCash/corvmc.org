<?php

namespace CorvMC\SpaceManagement\Services;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Contracts\ConflictCheckerInterface;

class ConflictChecker implements ConflictCheckerInterface
{
    public function checkConflicts(Carbon $start, Carbon $end, ?int $excludeReservationId = null): array
    {
        return ReservationService::checkForConflicts($start, $end, $excludeReservationId);
    }
}
