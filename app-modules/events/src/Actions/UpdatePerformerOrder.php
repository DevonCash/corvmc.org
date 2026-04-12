<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;

/**
 * @deprecated Use EventService::updatePerformerOrder() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class UpdatePerformerOrder
{
    /**
     * @deprecated Use EventService::updatePerformerOrder() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->updatePerformerOrder(...$args);
    }
}
