<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\States\OrderState;

class Cancelled extends OrderState
{
    public static $name = 'cancelled';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'tabler-x';
    }

    public function getLabel(): string
    {
        return 'Cancelled';
    }

    public function getDescription(): string
    {
        return 'Terminated before settlement';
    }

    // Transition hooks will be filled in during Epic 9
    // entering(): cascade Pending Transactions to Cancelled, reverse credit deductions, cancel Tickets
}
