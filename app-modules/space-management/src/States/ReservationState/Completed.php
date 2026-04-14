<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\States\ReservationState;

class Completed extends ReservationState
{
    public static $name = 'completed';

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'tabler-checkbox';
    }

    public function label(): string
    {
        return 'Completed';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function canBeModified(): bool
    {
        return false;
    }
}
