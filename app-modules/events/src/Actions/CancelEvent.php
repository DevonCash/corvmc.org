<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;

/**
 * @deprecated Use EventService::cancel() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class CancelEvent
{
    /**
     * @deprecated Use EventService::cancel() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->cancel(...$args);
    }
}
