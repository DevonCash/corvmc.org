<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\EventReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class GetConflictsForDate
{
    use AsAction;

    /**
     * Get all reservations and productions for a date in a single batch.
     *
     * Returns a DTO with pre-computed periods for fast in-memory overlap checking.
     */
    public function handle(Carbon $date, ?int $excludeReservationId = null): ConflictData
    {
        $bufferMinutes = app(ReservationSettings::class)->buffer_minutes;

        $reservations = $this->getReservationsForDate($date, $excludeReservationId);
        $productions = $this->getProductionsForDate($date);

        return new ConflictData($reservations, $productions, $bufferMinutes);
    }

    private function getReservationsForDate(Carbon $date, ?int $excludeReservationId): Collection
    {
        $cacheKey = 'reservations.conflicts.'.$date->format('Y-m-d');

        $dayReservations = Cache::remember($cacheKey, 1800, function () use ($date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            return Reservation::with('reservable')
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->get();
        });

        if ($excludeReservationId) {
            return $dayReservations->reject(fn (Reservation $r) => $r->id === $excludeReservationId);
        }

        return $dayReservations;
    }

    private function getProductionsForDate(Carbon $date): Collection
    {
        $cacheKey = 'event-reservations.conflicts.'.$date->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            return EventReservation::query()
                ->where('status', '!=', 'cancelled')
                ->where('reserved_until', '>', $dayStart)
                ->where('reserved_at', '<', $dayEnd)
                ->with('event')
                ->get();
        });
    }
}

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
