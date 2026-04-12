<?php

namespace CorvMC\SpaceManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * ConflictData class for efficient conflict checking
 */
class ConflictData
{
    /** @var array<int, Period> */
    private array $reservationPeriods = [];

    /** @var array<int, Period> */
    private array $productionPeriods = [];

    private int $bufferMinutes;

    public function __construct(
        public readonly Collection $reservations,
        public readonly Collection $productions,
        int $bufferMinutes
    ) {
        $this->bufferMinutes = $bufferMinutes;

        // Pre-compute periods for fast overlap checking
        foreach ($reservations as $reservation) {
            $period = $reservation->createPeriod();
            if ($period) {
                $this->reservationPeriods[] = $period;
            }
        }

        foreach ($productions as $production) {
            $period = $production->createPeriod();
            if ($period) {
                $this->productionPeriods[] = $period;
            }
        }
    }

    /**
     * Check if a time slot has any conflicts.
     */
    public function hasConflict(Carbon $start, Carbon $end): bool
    {
        if ($end <= $start) {
            return true;
        }

        $bufferedStart = $start->copy()->subMinutes($this->bufferMinutes);
        $bufferedEnd = $end->copy()->addMinutes($this->bufferMinutes);

        $testPeriod = Period::make($bufferedStart, $bufferedEnd, Precision::MINUTE());

        foreach ($this->reservationPeriods as $period) {
            if ($testPeriod->overlapsWith($period)) {
                return true;
            }
        }

        foreach ($this->productionPeriods as $period) {
            if ($testPeriod->overlapsWith($period)) {
                return true;
            }
        }

        return false;
    }
}