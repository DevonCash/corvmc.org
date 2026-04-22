<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\States\ReservationState;

class Scheduled extends ReservationState
{
    public static $name = 'pending';

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'tabler-calendar-event';
    }

    public function label(): string
    {
        return 'Scheduled';
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }
}
