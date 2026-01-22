<?php

namespace App\Listeners;

use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use App\Models\Venue;
use CorvMC\Events\Events\EventScheduling;
use CorvMC\Events\Exceptions\SchedulingConflictException;

/**
 * Checks for space reservation conflicts when scheduling an event.
 *
 * This listener bridges the Events module with the main app's reservation
 * system, throwing SchedulingConflictException if the event would conflict
 * with existing reservations.
 */
class CheckEventSpaceConflicts
{
    public function handle(EventScheduling $event): void
    {
        // Only check conflicts for CMC venue events with valid time range
        if (! $event->startTime || ! $event->endTime || ! $event->venueId) {
            return;
        }

        $venue = Venue::find($event->venueId);
        if (! $venue || ! $venue->is_cmc) {
            return;
        }

        $conflicts = GetAllConflicts::run($event->startTime, $event->endTime);

        if ($conflicts['reservations']->isNotEmpty()) {
            throw new SchedulingConflictException(
                'Event conflicts with existing reservation',
                ['reservations' => $conflicts['reservations']->toArray()]
            );
        }

        if ($conflicts['productions']->isNotEmpty()) {
            throw new SchedulingConflictException(
                'Event conflicts with existing event',
                ['productions' => $conflicts['productions']->toArray()]
            );
        }
    }
}
