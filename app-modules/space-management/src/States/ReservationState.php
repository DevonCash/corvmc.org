<?php

namespace CorvMC\SpaceManagement\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

use CorvMC\SpaceManagement\States\ReservationState\{
    Cancelled,
    Completed,
    Confirmed,
    Reserved,
    Scheduled
};

use CorvMC\SpaceManagement\States\ReservationState\Transitions\{
    CancelledTransition,
    CompletedTransition,
    ConfirmedTransition
};

abstract class ReservationState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ReservationState\Scheduled::class)
            ->registerState(ReservationState\Scheduled::class)
            ->registerState(ReservationState\Reserved::class)
            ->registerState(ReservationState\Confirmed::class)
            ->registerState(ReservationState\Cancelled::class)
            ->registerState(ReservationState\Completed::class)
            ->allowTransition(Scheduled::class, Confirmed::class, ConfirmedTransition::class)
            ->allowTransition(Reserved::class, Confirmed::class, ConfirmedTransition::class)
            ->allowTransition(Confirmed::class, Completed::class, CompletedTransition::class)
            ->allowTransition([Scheduled::class, Reserved::class, Confirmed::class], Cancelled::class, CancelledTransition::class);
    }

    abstract public function color(): string;

    abstract public function icon(): string;

    abstract public function label(): string;

    /**
     * Check if this reservation is in an active state (not cancelled or completed).
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * Check if this reservation requires confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return false;
    }

    /**
     * Check if this reservation can be modified (rescheduled).
     */
    public function canBeModified(): bool
    {
        return $this->isActive();
    }

    /**
     * Get the database value for this state.
     */
    public function getValue(): string
    {
        return $this->value ?? $this->getMorphClass();
    }
}
