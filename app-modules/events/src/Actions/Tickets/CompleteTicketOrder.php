<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;

/**
 * @deprecated Use TicketService::completeOrder() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class CompleteTicketOrder
{
    /**
     * @deprecated Use TicketService::completeOrder() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->completeOrder(...$args);
    }
}
