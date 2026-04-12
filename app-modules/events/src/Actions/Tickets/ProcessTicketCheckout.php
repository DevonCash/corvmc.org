<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;

/**
 * @deprecated Use TicketService::processCheckout() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class ProcessTicketCheckout
{
    /**
     * @deprecated Use TicketService::processCheckout() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->processCheckout(...$args);
    }
}
