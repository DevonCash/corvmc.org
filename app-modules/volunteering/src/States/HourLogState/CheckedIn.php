<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class CheckedIn extends HourLogState
{
    public static $name = 'checked_in';

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'tabler-login';
    }

    public function getLabel(): string
    {
        return 'Checked In';
    }

    public function getDescription(): string
    {
        return 'Currently volunteering';
    }
}
