<?php

namespace CorvMC\SpaceManagement\Services;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\SpaceManagement\Contracts\ConflictCheckerInterface;

class ConflictChecker implements ConflictCheckerInterface
{
    public function checkConflicts(Carbon $start, Carbon $end, ?int $excludeReservationId = null): array
    {
        return GetAllConflicts::run($start, $end, $excludeReservationId);
    }
}
