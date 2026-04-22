<?php

namespace CorvMC\Finance\States\OrderState;

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

    // Transition hooks will be filled in during Epic 5
    // entering(): assert all payment Transactions Cleared and sum to total_amount
    // entered(): fire OrderSettled, dispatch receipt
}
