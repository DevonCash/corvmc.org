<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Models\Production;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Lorisleiva\Actions\Concerns\AsAction;

class FindAvailableGaps
{
    use AsAction;

    /**
     * Find gaps between reservations and productions for a given date using Period operations.
     */
    public function handle(Carbon $date, int $minimumDurationMinutes = 60): array
    {
        $businessHoursStart = $date->copy()->setTime(9, 0);
        $businessHoursEnd = $date->copy()->setTime(22, 0);
        $businessPeriod = \App\Facades\ReservationService::createPeriod($businessHoursStart, $businessHoursEnd);

        // Get all reservations and productions for the day
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $reservations = Reservation::where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
            ->orderBy('reserved_at')
            ->get();

        $productions = Production::where('end_time', '>', $dayStart)
            ->where('start_time', '<', $dayEnd)
            ->orderBy('start_time')
            ->get()
            ->filter(function (Production $production) {
                return $production->usesPracticeSpace();
            });

        // Combine all occupied periods
        $occupiedPeriods = collect();

        // Add reservation periods
        $reservations->each(function (Reservation $reservation) use ($occupiedPeriods) {
            $period = $reservation->getPeriod();
            if ($period) {
                $occupiedPeriods->push($period);
            }
        });

        // Add production periods
        $productions->each(function (Production $production) use ($occupiedPeriods) {
            $period = $production->getPeriod();
            if ($period) {
                $occupiedPeriods->push($period);
            }
        });

        if ($occupiedPeriods->isEmpty()) {
            return [$businessPeriod]; // Entire business day is available
        }

        // Use Period collection to find gaps
        $periodCollection = new PeriodCollection(...$occupiedPeriods->toArray());
        $gaps = $periodCollection->gaps($businessPeriod);

        return collect($gaps)
            ->filter(fn(Period $gap) => $gap->length() >= $minimumDurationMinutes)
            ->values()
            ->toArray();
    }
}
