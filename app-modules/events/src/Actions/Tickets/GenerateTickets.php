<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateTickets
{
    use AsAction;

    /**
     * Generate individual ticket records for a completed order.
     *
     * Creates one Ticket record per quantity ordered. Each ticket has
     * a unique code for QR/barcode scanning at check-in.
     *
     * @param  TicketOrder  $order  The completed ticket order
     * @return Collection<int, Ticket> The generated tickets
     */
    public function handle(TicketOrder $order): Collection
    {
        $tickets = collect();

        for ($i = 0; $i < $order->quantity; $i++) {
            $ticket = Ticket::create([
                'ticket_order_id' => $order->id,
                'attendee_name' => $order->name,
                'attendee_email' => $order->email,
                'status' => TicketStatus::Valid,
            ]);

            $tickets->push($ticket);
        }

        return $tickets;
    }
}
