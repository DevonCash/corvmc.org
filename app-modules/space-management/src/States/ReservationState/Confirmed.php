<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\States\ReservationState;

class Confirmed extends ReservationState
{
    public static $name = 'confirmed';

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'tabler-calendar-check';
    }

    public function label(): string
    {
        return 'Confirmed';
    }
}
