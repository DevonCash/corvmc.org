<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Confirmed extends HourLogState
{
    public static $name = 'confirmed';

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'tabler-check';
    }

    public function getLabel(): string
    {
        return 'Confirmed';
    }

    public function getDescription(): string
    {
        return 'On the schedule';
    }
}
