<?php

namespace CorvMC\Support\Services;

use Carbon\Carbon;
use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Events\RecurringSeriesCancelled;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RRule\RRule;

/**
 * Service for managing recurring series and their instances.
 * 
 * This service handles the generation, calculation, and cancellation
 * of recurring patterns for various domain models (Reservations, Events, etc.).
 */
class RecurringService
{
    /**
     * Calculate occurrence dates from an RRULE recurrence pattern.
     *
     * @param string $ruleString The RRULE string
     * @param Carbon $start Start date for occurrences
     * @param Carbon $end End date for occurrences
     * @return Carbon[] Array of Carbon instances representing occurrence dates
     */
    public function calculateOccurrences(string $ruleString, Carbon $start, Carbon $end): array
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
     * Generate instances (Reservations, Events, etc.) for a recurring series.
     * Only generates up to max_advance_days into the future.
     *
     * @param RecurringSeries $series The recurring series to generate instances for
     * @return Collection Collection of created instances
     * @throws \RuntimeException If model doesn't implement Recurrable interface
     */
    public function generateInstances(RecurringSeries $series): Collection
    {
        $recurableType = Relation::getMorphedModel($series->recurable_type) ?? $series->recurable_type;

        if (! is_a($recurableType, Recurrable::class, true)) {
            throw new \RuntimeException(
                "Model {$recurableType} must implement ".Recurrable::class
            );
        }

        $startDate = $series->series_start_date;
        $maxDate = now()->addDays($series->max_advance_days);

        if ($series->series_end_date && $series->series_end_date->lt($maxDate)) {
            $maxDate = $series->series_end_date;
        }

        $occurrences = $this->calculateOccurrences($series->recurrence_rule, $startDate, $maxDate);
        $created = collect();

        foreach ($occurrences as $date) {
            // Check if instance already exists
            if ($recurableType::instanceExistsForDate($series, $date)) {
                continue;
            }

            // Try to create the actual instance
            try {
                $instance = $recurableType::createFromRecurringSeries($series, $date);
                $created->push($instance);
            } catch (\InvalidArgumentException $e) {
                // Conflict - create a placeholder to track skip
                $recurableType::createCancelledPlaceholder($series, $date);
            }
        }

        return $created;
    }

    /**
     * Cancel a recurring series and all its future instances.
     *
     * @param RecurringSeries $series The series to cancel
     * @param string|null $reason Optional cancellation reason
     * @throws \RuntimeException If model doesn't implement Recurrable interface
     */
    public function cancelSeries(RecurringSeries $series, ?string $reason = null): void
    {
        $recurableType = Relation::getMorphedModel($series->recurable_type) ?? $series->recurable_type;

        if (! is_a($recurableType, Recurrable::class, true)) {
            throw new \RuntimeException(
                "Model {$recurableType} must implement ".Recurrable::class
            );
        }

        DB::transaction(function () use ($series, $recurableType, $reason) {
            // Cancel the series itself
            $series->update(['status' => 'cancelled']);

            // Cancel all future instances
            $recurableType::cancelFutureInstances($series, $reason);
        });

        RecurringSeriesCancelled::dispatch($series);
    }
}