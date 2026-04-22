<?php

namespace CorvMC\Finance\Events;

use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Models\Charge;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAccepted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Chargeable $chargeable,
        public Charge $charge
    ) {
    }
}