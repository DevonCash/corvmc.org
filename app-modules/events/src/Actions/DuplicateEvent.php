<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventService::duplicate() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class DuplicateEvent
{
    use AsAction;

    /**
     * @deprecated Use EventService::duplicate() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->duplicate(...$args);
    }
}
