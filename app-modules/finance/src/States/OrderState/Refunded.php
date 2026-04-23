<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\Events\OrderRefunded;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\States\OrderState;

class Refunded extends OrderState
{
    public static $name = 'refunded';

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'tabler-receipt-refund';
    }

    public function getLabel(): string
    {
        return 'Refunded';
    }

    public function getDescription(): string
    {
        return 'Settled and then reversed';
    }

    public function entering(): void
    {
        $order = $this->getModel();

        // Reverse credit deductions — patron is being made whole
        Finance::reverseDiscountCredits($order, 'order_refunded');
    }

    public function entered(): void
    {
        $order = $this->getModel();

        OrderRefunded::dispatch($order);
    }
}
