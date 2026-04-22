<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\Events\OrderSettled;
use CorvMC\Finance\States\OrderState;

class Completed extends OrderState
{
    public static $name = 'completed';

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'tabler-circle-check';
    }

    public function getLabel(): string
    {
        return 'Completed';
    }

    public function getDescription(): string
    {
        return 'Payment rendered and settled';
    }

    public function entered(): void
    {
        $order = $this->getModel();
        $order->update(['settled_at' => now()]);

        OrderSettled::dispatch($order);
    }
}
