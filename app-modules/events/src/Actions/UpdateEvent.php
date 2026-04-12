<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventService::update() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class UpdateEvent
{
    use AsAction;

    /**
     * @deprecated Use EventService::update() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->update(...$args);
    }
}
