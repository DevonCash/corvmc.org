<?php

namespace CorvMC\Finance\Events;

use CorvMC\Finance\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an Order transitions to Refunded.
 *
 * Listeners in other modules use this to handle their own cleanup
 * (e.g. Events module cancels Tickets).
 */
class OrderRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }
}
