<?php

namespace CorvMC\Support\Actions;

use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate instances (Reservations, Events, etc.) for a recurring series.
 *
 * This action calls static methods on the recurable_type class directly.
 * The model must implement the Recurrable interface.
 */
class GenerateRecurringInstances
{
    use AsAction;

    /**
     * Generate instances for a recurring series.
     * Only generates up to max_advance_days into the future.
     */
    public function handle(RecurringSeries $series): Collection
    {
        $recurableType = $series->recurable_type;

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

        $occurrences = CalculateOccurrences::run($series->recurrence_rule, $startDate, $maxDate);
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
}
