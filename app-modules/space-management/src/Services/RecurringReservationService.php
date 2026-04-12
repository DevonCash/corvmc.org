<?php

namespace CorvMC\SpaceManagement\Services;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\Support\Actions\CalculateOccurrences;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Events\RecurringSeriesCreated;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Support\Collection;
use RRule\RRule;

/**
 * Service for managing recurring reservations.
 * 
 * This service handles the creation, validation, and management of
 * recurring reservation series for practice space bookings.
 */
class RecurringReservationService
{
    /**
     * Create a new recurring rehearsal series.
     *
     * @param array $data The recurring reservation data
     * @return RecurringSeries The created recurring series
     * @throws \InvalidArgumentException If user is not a sustaining member
     */
    public function createRecurringRehearsal(array $data): RecurringSeries
    {
        $series = RecurringSeries::create([
            'user_id' => $data['user_id'],
            'recurable_type' => 'rehearsal_reservation',
            'recurrence_rule' => new RRule([
                'FREQ' => $data['frequency'],
                'INTERVAL' => $data['interval'],
                'BYDAY' => $data['byday'] ?? null,
            ]),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'series_start_date' => $data['series_start_date'],
            'series_end_date' => $data['series_end_date'] ?? null,
            'max_advance_days' => $data['max_advance_days'] ?? 90,
            'status' => RecurringSeriesStatus::ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);

        // Generate initial instances
        GenerateRecurringInstances::run($series);

        RecurringSeriesCreated::dispatch($series);

        return $series->fresh();
    }

    /**
     * Build RRULE string from form inputs.
     *
     * @param array $data Form data with frequency, interval, and pattern details
     * @return string The RRULE string
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

    /**
     * Format an RRULE string into human-readable text.
     *
     * @param string $rrule The RRULE string
     * @return string Human-readable description
     */
    public function formatRRuleForHumans(string $rrule): string
    {
        try {
            $rule = new RRule($rrule);
            return $rule->humanReadable([
                'explicit_end' => false,
                'dtstart' => false,
            ]);
        } catch (\Exception $e) {
            return 'Custom recurrence pattern';
        }
    }

    /**
     * Validate a recurring pattern for conflicts.
     *
     * @param string $recurrenceRule The RRULE string
     * @param Carbon $seriesStartDate Start date of the series
     * @param Carbon|null $seriesEndDate End date of the series
     * @param string $startTime Start time (HH:MM format)
     * @param string $endTime End time (HH:MM format)
     * @param int $checkOccurrences Number of occurrences to check
     * @param int|null $excludeSeriesId Series ID to exclude from conflict check
     * @return array Array with 'errors' and 'warnings' collections
     */
    public function validateRecurringPattern(
        string $recurrenceRule,
        Carbon $seriesStartDate,
        ?Carbon $seriesEndDate,
        string $startTime,
        string $endTime,
        int $checkOccurrences = 8,
        ?int $excludeSeriesId = null
    ): array {
        $errors = collect();
        $warnings = collect();

        // Calculate the dates to check
        $maxDate = $seriesEndDate ?? $seriesStartDate->copy()->addMonths(3);
        $occurrences = collect(CalculateOccurrences::run($recurrenceRule, $seriesStartDate, $maxDate))
            ->take($checkOccurrences);

        foreach ($occurrences as $date) {
            $startDateTime = $date->copy()->setTimeFromTimeString($startTime);
            $endDateTime = $date->copy()->setTimeFromTimeString($endTime);

            // Check for one-off reservation/production/closure conflicts
            $conflicts = GetAllConflicts::run($startDateTime, $endDateTime);

            if ($conflicts['reservations']->isNotEmpty() || $conflicts['productions']->isNotEmpty() || $conflicts['closures']->isNotEmpty()) {
                $conflictDetails = $this->formatConflicts($conflicts);
                $warnings->push([
                    'date' => $date->format('M j, Y'),
                    'time' => $startDateTime->format('g:i A') . ' - ' . $endDateTime->format('g:i A'),
                    'conflicts' => $conflictDetails,
                    'type' => 'existing',
                ]);
            }

            // Check for recurring series conflicts
            $recurringConflicts = $this->checkRecurringSeriesConflicts(
                $date,
                $startTime,
                $endTime,
                $excludeSeriesId
            );

            if ($recurringConflicts->isNotEmpty()) {
                $warnings->push([
                    'date' => $date->format('M j, Y'),
                    'time' => $startDateTime->format('g:i A') . ' - ' . $endDateTime->format('g:i A'),
                    'conflicts' => $recurringConflicts->map(fn ($s) => $s->user->name . "'s recurring rehearsal")->join(', '),
                    'type' => 'recurring',
                ]);
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Pause a recurring series.
     *
     * @param RecurringSeries $series The series to pause
     * @param Carbon|null $pauseUntil Date to pause until
     * @return RecurringSeries The updated series
     */
    public function pauseRecurringSeries(RecurringSeries $series, ?Carbon $pauseUntil = null): RecurringSeries
    {
        $series->update([
            'status' => RecurringSeriesStatus::PAUSED,
            'paused_until' => $pauseUntil,
        ]);

        // Cancel any future instances
        $series->instances()
            ->where('reserved_at', '>', now())
            ->whereIn('status', ['scheduled', 'reserved'])
            ->update(['status' => 'cancelled']);

        return $series->fresh();
    }

    /**
     * Resume a paused recurring series.
     *
     * @param RecurringSeries $series The series to resume
     * @return RecurringSeries The updated series
     */
    public function resumeRecurringSeries(RecurringSeries $series): RecurringSeries
    {
        $series->update([
            'status' => RecurringSeriesStatus::ACTIVE,
            'paused_until' => null,
        ]);

        // Generate new future instances
        GenerateRecurringInstances::run($series);

        return $series->fresh();
    }

    /**
     * Extend a recurring series end date.
     *
     * @param RecurringSeries $series The series to extend
     * @param Carbon $newEndDate The new end date
     * @return RecurringSeries The updated series
     */
    public function extendRecurringSeries(RecurringSeries $series, Carbon $newEndDate): RecurringSeries
    {
        $series->update([
            'series_end_date' => $newEndDate,
        ]);

        // Generate additional instances for the extended period
        GenerateRecurringInstances::run($series);

        return $series->fresh();
    }

    /**
     * Update a recurring series.
     *
     * @param RecurringSeries $series The series to update
     * @param array $data The data to update
     * @return RecurringSeries The updated series
     */
    public function updateRecurringSeries(RecurringSeries $series, array $data): RecurringSeries
    {
        $series->update($data);

        // If time or pattern changed, regenerate instances
        if (isset($data['start_time']) || isset($data['end_time']) || isset($data['recurrence_rule'])) {
            // Cancel existing future instances
            $series->instances()
                ->where('reserved_at', '>', now())
                ->whereIn('status', ['scheduled', 'reserved'])
                ->update(['status' => 'cancelled']);

            // Generate new instances with updated pattern
            GenerateRecurringInstances::run($series);
        }

        return $series->fresh();
    }

    /**
     * Skip a specific instance in a recurring series.
     *
     * @param RecurringSeries $series The series
     * @param Carbon $instanceDate The date of the instance to skip
     * @return bool True if instance was skipped
     */
    public function skipRecurringInstance(RecurringSeries $series, Carbon $instanceDate): bool
    {
        $startOfDay = $instanceDate->copy()->startOfDay();
        $endOfDay = $instanceDate->copy()->endOfDay();

        $instance = $series->instances()
            ->whereBetween('reserved_at', [$startOfDay, $endOfDay])
            ->whereIn('status', ['scheduled', 'reserved'])
            ->first();

        if ($instance) {
            $instance->update([
                'status' => 'cancelled',
                'notes' => 'Skipped by user',
            ]);

            // Add to skipped dates
            $skippedDates = $series->skipped_dates ?? [];
            $skippedDates[] = $instanceDate->format('Y-m-d');
            $series->update(['skipped_dates' => array_unique($skippedDates)]);

            return true;
        }

        return false;
    }

    /**
     * Generate future instances for a recurring series.
     *
     * @param RecurringSeries $series The series to generate instances for
     * @return Collection The generated instances
     */
    public function generateFutureRecurringInstances(RecurringSeries $series): Collection
    {
        return GenerateRecurringInstances::run($series);
    }

    /**
     * Get upcoming instances for a recurring series.
     *
     * @param RecurringSeries $series The series
     * @param int $limit Number of instances to retrieve
     * @return Collection The upcoming instances
     */
    public function getUpcomingRecurringInstances(RecurringSeries $series, int $limit = 10): Collection
    {
        return $series->instances()
            ->where('reserved_at', '>', now())
            ->whereIn('status', ['scheduled', 'reserved'])
            ->orderBy('reserved_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Format conflicts for display.
     *
     * @param array $conflicts Array of conflict collections
     * @return string Formatted conflict description
     */
    protected function formatConflicts(array $conflicts): string
    {
        $parts = [];

        if ($conflicts['reservations']->isNotEmpty()) {
            $count = $conflicts['reservations']->count();
            $parts[] = $count . ' ' . str()->plural('reservation', $count);
        }

        if ($conflicts['productions']->isNotEmpty()) {
            $count = $conflicts['productions']->count();
            $parts[] = $count . ' ' . str()->plural('event', $count);
        }

        if ($conflicts['closures']->isNotEmpty()) {
            $parts[] = 'space closure';
        }

        return implode(', ', $parts);
    }

    /**
     * Check for conflicts with other recurring series.
     *
     * @param Carbon $date The date to check
     * @param string $startTime Start time
     * @param string $endTime End time
     * @param int|null $excludeSeriesId Series to exclude from check
     * @return Collection Collection of conflicting series
     */
    protected function checkRecurringSeriesConflicts(
        Carbon $date,
        string $startTime,
        string $endTime,
        ?int $excludeSeriesId = null
    ): Collection {
        $query = RecurringSeries::where('status', RecurringSeriesStatus::ACTIVE)
            ->where('recurable_type', 'rehearsal_reservation');

        if ($excludeSeriesId) {
            $query->where('id', '!=', $excludeSeriesId);
        }

        $potentialConflicts = $query->get();
        $conflicts = collect();

        foreach ($potentialConflicts as $series) {
            // Check if this series occurs on the given date
            $occurrences = CalculateOccurrences::run(
                $series->recurrence_rule,
                $series->series_start_date,
                $date->copy()->addDay()
            );

            $occursOnDate = collect($occurrences)->contains(fn ($d) => $d->isSameDay($date));

            if ($occursOnDate) {
                // Check if times overlap
                $seriesStart = Carbon::parse($series->start_time);
                $seriesEnd = Carbon::parse($series->end_time);
                $checkStart = Carbon::parse($startTime);
                $checkEnd = Carbon::parse($endTime);

                if (
                    ($checkStart < $seriesEnd && $checkEnd > $seriesStart) ||
                    ($seriesStart < $checkEnd && $seriesEnd > $checkStart)
                ) {
                    $conflicts->push($series);
                }
            }
        }

        return $conflicts;
    }
}