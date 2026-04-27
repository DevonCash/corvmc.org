<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Pending extends HourLogState
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
        return 'Awaiting staff review';
    }
}
