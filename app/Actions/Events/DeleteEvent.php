<?php

namespace App\Actions\Events;

use App\Models\Event;
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
