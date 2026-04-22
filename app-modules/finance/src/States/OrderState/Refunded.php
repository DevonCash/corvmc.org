<?php

namespace CorvMC\Finance\States\OrderState;

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

    // Transition hooks will be filled in during Epic 7
    // entering(): write compensating refund Transactions, reverse wallet withdrawals, cancel Tickets
}
