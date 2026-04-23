<?php

namespace CorvMC\Events\Listeners;

use CorvMC\Events\Models\TicketOrder;
use CorvMC\Events\Services\TicketService;
use CorvMC\Finance\Events\OrderSettled;

class GenerateTicketsOnOrderSettled
{
    public function handle(OrderSettled $event): void
    {
        $order = $event->order;

        // Check if this Order contains ticket line items
        $ticketLineItem = $order->lineItems
            ->first(fn ($li) => $li->product_type === 'event_ticket');

        if (! $ticketLineItem || ! $ticketLineItem->product_id) {
            return;
        }

        $ticketOrder = TicketOrder::find($ticketLineItem->product_id);

        if (! $ticketOrder) {
            return;
        }

        // Guard against duplicate ticket generation — check if tickets
        // already exist, not just status (handles race conditions)
        if ($ticketOrder->tickets()->exists()) {
            return;
        }

        $ticketOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        app(TicketService::class)->generateTickets($ticketOrder);
    }
}
