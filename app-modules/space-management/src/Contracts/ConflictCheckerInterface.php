<?php

namespace CorvMC\SpaceManagement\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface ConflictCheckerInterface
{
    /**
     * Check for conflicts with reservations and productions.
     *
     * @return array{reservations: Collection, productions: Collection}
     */
    public function checkConflicts(Carbon $start, Carbon $end, ?int $excludeReservationId = null): array;
}
