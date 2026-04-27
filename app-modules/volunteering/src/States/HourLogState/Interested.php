<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Interested extends HourLogState
{
    public static $name = 'interested';

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'tabler-hand-stop';
    }

    public function getLabel(): string
    {
        return 'Interested';
    }

    public function getDescription(): string
    {
        return 'Signed up, awaiting confirmation';
    }
}
