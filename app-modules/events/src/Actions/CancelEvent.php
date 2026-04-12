<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventService::cancel() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class CancelEvent
{
    use AsAction;

    /**
     * @deprecated Use EventService::cancel() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->cancel(...$args);
    }
}
