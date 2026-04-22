<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\States\OrderState;

class Pending extends OrderState
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
        return 'Awaiting payment or commitment';
    }
}
