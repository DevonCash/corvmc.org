<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Approved extends HourLogState
{
    public static $name = 'approved';

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
        return 'Approved';
    }

    public function getDescription(): string
    {
        return 'Hours confirmed by staff';
    }
}
