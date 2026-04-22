<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\States\ReservationState;

class Reserved extends ReservationState
{
    public static $name = 'reserved';

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'tabler-hourglass';
    }

    public function label(): string
    {
        return 'Reserved';
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function canConfirm(): bool
    {
        return true;
    }

}
