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

    public function requiresConfirmation(): bool
    {
        return false;
    }

    /**
     * Set confirmed_at timestamp when entering confirmed state.
     */
    public function onEntry(): void
    {
        if (!$this->model->confirmed_at) {
            $this->model->confirmed_at = now();
            $this->model->saveQuietly();
        }
    }
}
