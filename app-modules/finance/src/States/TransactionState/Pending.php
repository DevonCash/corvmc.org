<?php

namespace CorvMC\Finance\States\TransactionState;

use CorvMC\Finance\States\TransactionState;

class Pending extends TransactionState
{
    public static $name = 'pending';

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'tabler-clock';
    }

    public function getLabel(): string
    {
        return 'Pending';
    }

    public function getDescription(): string
    {
        return 'Awaiting settlement';
    }
}
