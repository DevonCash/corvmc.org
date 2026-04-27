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

    /**
     * Fields that trigger activity logging when changed.
     */
    private const TRACKED_FIELDS = ['name', 'bio', 'genre', 'location', 'website'];

    public function handleUpdated(BandUpdated $event): void
    {
        $band = $event->band;
        $trackedChanges = array_intersect($event->changedFields, self::TRACKED_FIELDS);

        if (empty($trackedChanges)) {
            return;
        }

        $summary = implode(', ', $trackedChanges);
        $oldValues = array_intersect_key($event->oldValues, array_flip($trackedChanges));

        activity('band')
            ->performedOn($band)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'changed_fields' => array_values($trackedChanges),
                'old_values' => $oldValues,
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
