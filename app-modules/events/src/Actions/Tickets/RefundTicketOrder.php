<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TicketService::refundOrder() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class RefundTicketOrder
{
    use AsAction;

    /**
     * @deprecated Use TicketService::refundOrder() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->refundOrder(...$args);
    }
}
