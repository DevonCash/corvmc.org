<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Services\TicketService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use TicketService::generateTickets() instead
 * This action is maintained for backward compatibility only.
 * New code should use the TicketService directly.
 */
class GenerateTickets
{
    use AsAction;

    /**
     * @deprecated Use TicketService::generateTickets() instead
     */
    public function handle(...$args)
    {
        return app(TicketService::class)->generateTickets(...$args);
    }
}
