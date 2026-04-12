<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;

/**
 * @deprecated Use EventService::delete() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class DeleteEvent
{
    /**
     * @deprecated Use EventService::delete() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->delete(...$args);
    }
}
