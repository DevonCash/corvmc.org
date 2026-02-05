<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\Support\Actions\CalculateOccurrences;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateRecurringPattern
{
    use AsAction;

    /**
     * Validate a recurring pattern for conflicts.
     *
     * Returns an array with 'errors' and 'warnings' collections.
     * Each item contains 'date', 'time', and 'conflicts' keys.
     *
     * @param  int  $checkOccurrences  Number of occurrences to check
     */
    public function handle(
        string $recurrenceRule,
        Carbon $seriesStartDate,
        ?Carbon $seriesEndDate,
        string $startTime,
        string $endTime,
        int $checkOccurrences = 8,
        ?int $excludeSeriesId = null,
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
                    'time' => $startDateTime->format('g:i A').' - '.$endDateTime->format('g:i A'),
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
                    'time' => $startDateTime->format('g:i A').' - '.$endDateTime->format('g:i A'),
                    'conflicts' => $recurringConflicts->map(fn ($s) => $s->user->name."'s recurring rehearsal")->join(', '),
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
     * Check if any other active recurring series would conflict on this date/time.
     */
    protected function checkRecurringSeriesConflicts(
        Carbon $date,
        string $startTime,
        string $endTime,
        ?int $excludeSeriesId = null
    ): Collection {
        $conflictingSeries = collect();

        // Get all active recurring series for rehearsal reservations
        $query = RecurringSeries::where('recurable_type', 'rehearsal_reservation')
            ->where('status', RecurringSeriesStatus::ACTIVE)
            ->where('series_start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('series_end_date')
                    ->orWhere('series_end_date', '>=', $date);
            });

        if ($excludeSeriesId) {
            $query->where('id', '!=', $excludeSeriesId);
        }

        $activeSeries = $query->with('user')->get();

        foreach ($activeSeries as $series) {
            // Check if this series has an occurrence on the same date
            $seriesOccurrences = collect(CalculateOccurrences::run(
                $series->recurrence_rule,
                $series->series_start_date,
                $date->copy()->addDay()
            ));

            $hasOccurrenceOnDate = $seriesOccurrences->contains(function ($occurrence) use ($date) {
                return $occurrence->isSameDay($date);
            });

            if (! $hasOccurrenceOnDate) {
                continue;
            }

            // Check time overlap
            if ($this->timesOverlap($startTime, $endTime, $series->start_time->format('H:i:s'), $series->end_time->format('H:i:s'))) {
                $conflictingSeries->push($series);
            }
        }

        return $conflictingSeries;
    }

    /**
     * Check if two time ranges overlap.
     */
    protected function timesOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = Carbon::parse($start1);
        $e1 = Carbon::parse($end1);
        $s2 = Carbon::parse($start2);
        $e2 = Carbon::parse($end2);

        return $s1 < $e2 && $s2 < $e1;
    }

    /**
     * Format conflicts into a readable string.
     */
    protected function formatConflicts(array $conflicts): string
    {
        $messages = [];

        if ($conflicts['reservations']->isNotEmpty()) {
            $names = $conflicts['reservations']->map(fn ($r) => $r->user?->name ?? 'Unknown')->unique()->join(', ');
            $messages[] = "reservation by {$names}";
        }

        if ($conflicts['productions']->isNotEmpty()) {
            $titles = $conflicts['productions']->map(fn ($p) => $p->title)->join(', ');
            $messages[] = "production: {$titles}";
        }

        if ($conflicts['closures']->isNotEmpty()) {
            $messages[] = 'space closure';
        }

        return implode(', ', $messages);
    }
}
