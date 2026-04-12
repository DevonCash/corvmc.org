<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Services\EventService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EventService::getInterestedUsers() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EventService directly.
 */
class GetInterestedUsers
{
    use AsAction;

    /**
     * @deprecated Use EventService::getInterestedUsers() instead
     */
    public function handle(...$args)
    {
        return app(EventService::class)->getInterestedUsers(...$args);
    }
}
