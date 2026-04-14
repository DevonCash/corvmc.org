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

    /**
     * Set cancelled_at timestamp when entering cancelled state.
     */
    public function onEntry(): void
    {
        if (!$this->model->cancelled_at) {
            $this->model->cancelled_at = now();
            $this->model->saveQuietly();
        }
    }
}
