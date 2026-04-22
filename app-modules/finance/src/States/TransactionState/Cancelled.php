<?php

namespace CorvMC\Finance\States\TransactionState;

use CorvMC\Finance\States\TransactionState;

class Cancelled extends TransactionState
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
        return 'Voided before settlement';
    }
}
