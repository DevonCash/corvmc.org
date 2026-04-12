<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;

/**
 * @deprecated Use EventService::update() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class UpdateEvent
{
    /**
     * @deprecated Use EventService::update() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->update(...$args);
    }
}
