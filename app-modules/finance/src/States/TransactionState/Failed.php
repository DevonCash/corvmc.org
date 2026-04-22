<?php

namespace CorvMC\Finance\States\TransactionState;

use CorvMC\Finance\States\TransactionState;

class Failed extends TransactionState
{
    public static $name = 'failed';

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'tabler-alert-triangle';
    }

    public function getLabel(): string
    {
        return 'Failed';
    }

    public function getDescription(): string
    {
        return 'Payment rail rejected';
    }
}
