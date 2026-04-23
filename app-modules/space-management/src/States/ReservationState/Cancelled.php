<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

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

    public function entering(): void
    {
        // The status column itself records that this is cancelled.
        // No additional timestamp column exists on reservations.
    }
}
