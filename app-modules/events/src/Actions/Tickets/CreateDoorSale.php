<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TicketService::createDoorSale() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class CreateDoorSale
{
    use AsAction;

    /**
     * @deprecated Use TicketService::createDoorSale() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->createDoorSale(...$args);
    }
}
