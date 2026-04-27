<?php

namespace CorvMC\Volunteering\States\HourLogState;

use CorvMC\Volunteering\States\HourLogState;

class Rejected extends HourLogState
{
    public static $name = 'rejected';

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'tabler-circle-x';
    }

    public function getLabel(): string
    {
        return 'Rejected';
    }

    public function getDescription(): string
    {
        return 'Hours denied by staff';
    }
}
