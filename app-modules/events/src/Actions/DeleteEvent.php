<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEvent
{
    use AsAction;

    /**
     * Delete an event.
     */
    public function handle(Event $event): void
    {
        $event->delete();
    }
}
