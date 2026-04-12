<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;

/**
 * @deprecated Use TicketService::createOrder() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class CreateTicketOrder
{
    /**
     * @deprecated Use TicketService::createOrder() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->createOrder(...$args);
    }
}
