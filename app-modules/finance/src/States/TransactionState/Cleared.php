<?php

namespace CorvMC\Finance\States\TransactionState;

use CorvMC\Finance\States\TransactionState;

class Cleared extends TransactionState
{
    public static $name = 'cleared';

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
        return 'Cleared';
    }

    public function getDescription(): string
    {
        return 'Payment settled';
    }
}
