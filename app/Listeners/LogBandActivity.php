<?php

namespace App\Listeners;

use CorvMC\Bands\Events\BandCreated;
use CorvMC\Bands\Events\BandDeleted;
use CorvMC\Bands\Events\BandUpdated;

class LogBandActivity
{
    public function handleCreated(BandCreated $event): void
    {
        $band = $event->band;

        activity('band')
            ->performedOn($band)
            ->causedBy(auth()->user())
            ->event('created')
            ->log("Band created: {$band->name}");
    }

    public function handleUpdated(BandUpdated $event): void
    {
        $band = $event->band;
        $summary = implode(', ', $event->changedFields);

        activity('band')
            ->performedOn($band)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'changed_fields' => $event->changedFields,
                'old_values' => $event->oldValues,
            ])
            ->log("Band updated: {$summary}");
    }

    public function handleDeleted(BandDeleted $event): void
    {
        $band = $event->band;

        activity('band')
            ->performedOn($band)
            ->causedBy(auth()->user())
            ->event('deleted')
            ->log("Band deleted: {$band->name}");
    }
}
