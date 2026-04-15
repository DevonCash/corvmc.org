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

    /**
     * Reserved status requires confirmation within 3 days or auto-cancellation.
     */
    public function onEntry(): void
    {
        // Log that credits are deferred for Reserved status
        activity('reservation')
            ->performedOn($this->model)
            ->event('reserved')
            ->log('Reservation reserved - confirmation required within 3 days');
    }
}
