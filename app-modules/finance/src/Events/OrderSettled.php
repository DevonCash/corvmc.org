<?php

namespace CorvMC\Finance\Events;

use CorvMC\Finance\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an Order reaches a settled state (Completed or Comped).
 *
 * Replaces PaymentAccepted. Listeners use this to activate Tickets,
 * dispatch receipt notifications, and trigger downstream integrations.
 */
class OrderSettled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }
}
