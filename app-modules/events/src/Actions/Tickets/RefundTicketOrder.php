<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;

/**
 * @deprecated Use TicketService::refundOrder() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class RefundTicketOrder
{
    /**
     * @deprecated Use TicketService::refundOrder() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->refundOrder(...$args);
    }
}
