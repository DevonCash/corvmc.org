<?php

namespace CorvMC\Events\Events;

use CorvMC\Events\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an event is cancelled.
 */
class EventCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Event $event,
    ) {}
}
