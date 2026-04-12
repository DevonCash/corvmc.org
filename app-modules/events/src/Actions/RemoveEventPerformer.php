<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;

/**
 * @deprecated Use EventService::removePerformer() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class RemoveEventPerformer
{
    /**
     * @deprecated Use EventService::removePerformer() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->removePerformer(...$args);
    }
}
