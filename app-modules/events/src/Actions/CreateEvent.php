<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Models\Event;
use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new event.
 *
 * @deprecated Use EventService::create() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class CreateEvent
{
    use AsAction;

    /**
     * Create a new event.
     * 
     * @deprecated Use EventService::create() instead
     */
    public function handle(array $data): Event
    {
        return app(EventService::class)->create($data);
    }
}
