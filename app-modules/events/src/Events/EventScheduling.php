<?php

namespace CorvMC\Events\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before an event is created to allow listeners to validate scheduling.
 *
 * Listeners can throw SchedulingConflictException to prevent the event from being created.
 * This allows external modules (like SpaceManagement) to check for conflicts without
 * the Events module needing to know about them.
 */
class EventScheduling
{
    use Dispatchable;

    public function __construct(
        public readonly array $data,
        public readonly ?Carbon $startTime,
        public readonly ?Carbon $endTime,
        public readonly ?int $venueId
    ) {}

    /**
     * Check if this event is at a CMC venue (useful for listeners).
     */
    public function isAtCmcVenue(): bool
    {
        if (! $this->venueId) {
            return false;
        }

        // Listeners should check the venue themselves if they need to
        return true;
    }
}
