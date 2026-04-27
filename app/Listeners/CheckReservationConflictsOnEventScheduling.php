<?php

namespace App\Listeners;

use CorvMC\Events\Events\EventScheduling;
use CorvMC\Events\Exceptions\SchedulingConflictException;
use CorvMC\Events\Models\Venue;
use CorvMC\SpaceManagement\Facades\ReservationService;

class CheckReservationConflictsOnEventScheduling
{
    public function handle(EventScheduling $event): void
    {
        if (! $event->venueId || ! $event->startTime || ! $event->endTime) {
            return;
        }

        $venue = Venue::find($event->venueId);

        if (! $venue || ! $venue->is_cmc) {
            return;
        }

        $conflicts = ReservationService::getConflicts($event->startTime, $event->endTime);

        if ($conflicts->isNotEmpty()) {
            throw new SchedulingConflictException(
                'Event conflicts with existing reservation(s)',
                $conflicts->pluck('id')->all()
            );
        }
    }
}
