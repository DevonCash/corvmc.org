<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\States\ReservationState;

class Cancelled extends ReservationState
{
    public static $name = 'cancelled';

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'tabler-calendar-cancel';
    }

    public function label(): string
    {
        return 'Cancelled';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function canBeModified(): bool
    {
        return false;
    }

    public function entered(): void
    {
        ReservationCancelled::dispatch($this->getModel());
    }
}
