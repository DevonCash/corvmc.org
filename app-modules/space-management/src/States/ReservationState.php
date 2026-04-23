<?php

namespace CorvMC\SpaceManagement\States;

use CorvMC\SpaceManagement\Models\Reservation;
use Spatie\ModelStates\StateConfig;
use CorvMC\Support\States\BaseState;
use CorvMC\SpaceManagement\States\ReservationState\{
    Cancelled,
    Completed,
    Confirmed,
    Reserved,
    Scheduled
};

abstract class ReservationState extends BaseState
{
    public Reservation $model;

    public function canConfirm(): bool
    {
        return $this->canTransitionTo(Confirmed::class);
    }

    public function isActive(): bool
    {
        return ! $this->isFinal();
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Scheduled::class)
            ->registerStatesFromDirectory(__DIR__ . '/ReservationState')
            ->allowTransition(Scheduled::class, Confirmed::class)
            ->allowTransition(Reserved::class, Confirmed::class)
            ->allowTransition(Confirmed::class, Completed::class)
            ->allowTransition([Scheduled::class, Reserved::class, Confirmed::class], Cancelled::class);
    }
}
