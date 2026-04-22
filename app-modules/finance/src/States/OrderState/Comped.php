<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\States\OrderState;

class Comped extends OrderState
{
    public static $name = 'comped';

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'tabler-gift';
    }

    public function getLabel(): string
    {
        return 'Comped';
    }

    public function getDescription(): string
    {
        return 'Order waived — no payment';
    }

    // Transition hooks will be filled in during Epic 5
    // entering(): cascade Pending Transactions to Cancelled
    // entered(): fire OrderSettled
}
