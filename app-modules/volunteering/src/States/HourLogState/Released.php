<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Released extends HourLogState
{
    public static $name = 'released';

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
        return 'Released';
    }

    public function getDescription(): string
    {
        return 'No longer needed for this shift';
    }
}
