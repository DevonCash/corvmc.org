<?php

namespace App\Listeners;

use CorvMC\Support\Events\RecurringSeriesCancelled;
use CorvMC\Support\Events\RecurringSeriesCreated;
use CorvMC\Support\Events\RecurringSeriesExtended;
use CorvMC\Support\Events\RecurringSeriesPaused;
use CorvMC\Support\Events\RecurringSeriesResumed;

class LogRecurringSeriesActivity
{
    public function handleCreated(RecurringSeriesCreated $event): void
    {
        $series = $event->series;

        activity('recurring_series')
            ->performedOn($series)
            ->causedBy(auth()->user())
            ->event('created')
            ->withProperties([
                'recurrence_rule' => (string) $series->recurrence_rule,
                'recurable_type' => $series->recurable_type,
            ])
            ->log("Recurring series created: {$series->recurrence_rule}");
    }

    public function handleCancelled(RecurringSeriesCancelled $event): void
    {
        $series = $event->series;

        activity('recurring_series')
            ->performedOn($series)
            ->causedBy(auth()->user())
            ->event('cancelled')
            ->log('Recurring series cancelled');
    }

    public function handlePaused(RecurringSeriesPaused $event): void
    {
        $series = $event->series;

        activity('recurring_series')
            ->performedOn($series)
            ->causedBy(auth()->user())
            ->event('paused')
            ->log('Recurring series paused');
    }

    public function handleResumed(RecurringSeriesResumed $event): void
    {
        $series = $event->series;

        activity('recurring_series')
            ->performedOn($series)
            ->causedBy(auth()->user())
            ->event('resumed')
            ->log('Recurring series resumed');
    }

    public function handleExtended(RecurringSeriesExtended $event): void
    {
        $series = $event->series;
        $newEndDate = $series->series_end_date->format('M j, Y');

        activity('recurring_series')
            ->performedOn($series)
            ->causedBy(auth()->user())
            ->event('extended')
            ->withProperties([
                'previous_end_date' => $event->previousEndDate->toIso8601String(),
                'new_end_date' => $series->series_end_date->toIso8601String(),
            ])
            ->log("Recurring series extended to {$newEndDate}");
    }
}
