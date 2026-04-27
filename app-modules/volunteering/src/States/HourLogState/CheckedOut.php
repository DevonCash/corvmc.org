<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class CheckedOut extends HourLogState
{
    public static $name = 'checked_out';

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'tabler-logout';
    }

    public function getLabel(): string
    {
        return 'Checked Out';
    }

    public function getDescription(): string
    {
        return 'Shift completed';
    }
}
