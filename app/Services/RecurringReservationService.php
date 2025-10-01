<?php

namespace App\Services;

use App\Models\RecurringReservation;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RRule\RRule;

/**
 * Service for managing recurring reservation series and their instances.
 */
class RecurringReservationService
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * Create a new recurring reservation series.
     */
    public function createRecurringSeries(
        User $user,
        string $recurrenceRule,
        Carbon $startDate,
        string $startTime,
        string $endTime,
        ?Carbon $endDate = null,
        int $maxAdvanceDays = 90,
        ?string $notes = null
    ): RecurringReservation {
        if (!$user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        // Calculate duration in minutes
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationMinutes = $start->diffInMinutes($end);

        $series = RecurringReservation::create([
            'user_id' => $user->id,
            'recurrence_rule' => $recurrenceRule,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'series_start_date' => $startDate,
            'series_end_date' => $endDate,
            'max_advance_days' => $maxAdvanceDays,
            'status' => 'active',
            'notes' => $notes,
        ]);

        // Generate initial instances
        $this->generateInstances($series);

        return $series->fresh();
    }

    /**
     * Generate reservation instances for a recurring series.
     * Only generates up to max_advance_days into the future.
     */
    public function generateInstances(RecurringReservation $series): Collection
    {
        $startDate = $series->series_start_date;
        $maxDate = now()->addDays($series->max_advance_days);

        if ($series->series_end_date && $series->series_end_date->lt($maxDate)) {
            $maxDate = $series->series_end_date;
        }

        $occurrences = $this->calculateOccurrences($series->recurrence_rule, $startDate, $maxDate);
        $created = collect();

        foreach ($occurrences as $date) {
            // Check if instance already exists
            $existing = Reservation::where('recurring_reservation_id', $series->id)
                ->where('instance_date', $date->toDateString())
                ->first();

            if ($existing) {
                continue;
            }

            // Try to create the actual reservation
            try {
                $reservation = $this->createInstanceReservation($series, $date);
                $created->push($reservation);
            } catch (\InvalidArgumentException $e) {
                // Conflict - create a placeholder cancelled reservation to track skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_reservation_id' => $series->id,
                    'instance_date' => $date->toDateString(),
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s')),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s')),
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Scheduling conflict',
                    'is_recurring' => true,
                    'cost' => 0,
                ]);
            }
        }

        return $created;
    }

    /**
     * Create a single reservation instance from recurring pattern.
     */
    protected function createInstanceReservation(
        RecurringReservation $series,
        Carbon $date
    ): Reservation {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        return $this->reservationService->createReservation(
            $series->user,
            $startDateTime,
            $endDateTime,
            [
                'recurring_reservation_id' => $series->id,
                'instance_date' => $date->toDateString(),
                'is_recurring' => true,
                'recurrence_pattern' => ['source' => 'recurring_series'],
                'status' => 'pending',
            ]
        );
    }

    /**
     * Calculate occurrence dates from recurrence rule using php-rrule library.
     */
    protected function calculateOccurrences(string $ruleString, Carbon $start, Carbon $end): array
    {
        // Parse RRULE using rlanvin/php-rrule
        $rrule = new RRule($ruleString, $start->toDateTimeString());

        $occurrences = [];
        foreach ($rrule as $occurrence) {
            $carbonOccurrence = Carbon::instance($occurrence);

            if ($carbonOccurrence->gt($end)) {
                break;
            }

            if ($carbonOccurrence->gte($start)) {
                $occurrences[] = $carbonOccurrence->copy();
            }
        }

        return $occurrences;
    }

    /**
     * Cancel entire recurring series.
     */
    public function cancelSeries(RecurringReservation $series, ?string $reason = null): void
    {
        DB::transaction(function () use ($series, $reason) {
            // Cancel series
            $series->update(['status' => 'cancelled']);

            // Cancel all future instances
            $futureReservations = Reservation::where('recurring_reservation_id', $series->id)
                ->where('reserved_at', '>', now())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get();

            foreach ($futureReservations as $reservation) {
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Recurring series cancelled',
                ]);
            }
        });
    }

    /**
     * Skip a single instance without cancelling series.
     */
    public function skipInstance(RecurringReservation $series, Carbon $date, ?string $reason = null): void
    {
        $reservation = Reservation::where('recurring_reservation_id', $series->id)
            ->where('instance_date', $date->toDateString())
            ->first();

        DB::transaction(function () use ($reservation, $series, $date, $reason) {
            if ($reservation) {
                // Cancel existing reservation
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Manually skipped',
                ]);
            } else {
                // Create placeholder cancelled reservation to track manual skip
                Reservation::create([
                    'user_id' => $series->user_id,
                    'recurring_reservation_id' => $series->id,
                    'instance_date' => $date->toDateString(),
                    'reserved_at' => $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s')),
                    'reserved_until' => $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s')),
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason ?? 'Manually skipped',
                    'is_recurring' => true,
                    'cost' => 0,
                ]);
            }
        });
    }

    /**
     * Extend series end date.
     */
    public function extendSeries(RecurringReservation $series, Carbon $newEndDate): void
    {
        $series->update(['series_end_date' => $newEndDate]);

        // Generate new instances
        $this->generateInstances($series);
    }

    /**
     * Get upcoming instances for a series.
     */
    public function getUpcomingInstances(RecurringReservation $series, int $limit = 10): Collection
    {
        return Reservation::where('recurring_reservation_id', $series->id)
            ->where('instance_date', '>=', now()->toDateString())
            ->orderBy('instance_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Scheduled job: Generate future instances for all active series.
     */
    public function generateFutureInstances(): void
    {
        $activeSeries = RecurringReservation::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('series_end_date')
                  ->orWhere('series_end_date', '>', now());
            })
            ->get();

        foreach ($activeSeries as $series) {
            $this->generateInstances($series);
        }
    }

    /**
     * Format RRULE string into human-readable text.
     */
    public function formatRuleForHumans(string $ruleString): string
    {
        try {
            $rrule = new RRule($ruleString);
            return $rrule->humanReadable();
        } catch (\Exception $e) {
            return $ruleString;
        }
    }

    /**
     * Build RRULE string from form inputs.
     */
    public function buildRRule(array $data): string
    {
        $parts = [];

        // Frequency (required)
        $parts[] = 'FREQ=' . strtoupper($data['frequency']);

        // Interval
        if (isset($data['interval']) && $data['interval'] > 1) {
            $parts[] = 'INTERVAL=' . $data['interval'];
        }

        // By day (for weekly)
        if (isset($data['by_day']) && is_array($data['by_day']) && count($data['by_day']) > 0) {
            $parts[] = 'BYDAY=' . implode(',', $data['by_day']);
        }

        // By month day (for monthly)
        if (isset($data['by_month_day'])) {
            $parts[] = 'BYMONTHDAY=' . $data['by_month_day'];
        }

        // By set pos (for "first Monday" patterns)
        if (isset($data['by_set_pos'])) {
            $parts[] = 'BYSETPOS=' . $data['by_set_pos'];
        }

        return implode(';', $parts);
    }
}
