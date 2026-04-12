<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventService::publish() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class PublishEvent
{
    use AsAction;

    /**
     * @deprecated Use EventService::publish() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->publish(...$args);
    }
}
