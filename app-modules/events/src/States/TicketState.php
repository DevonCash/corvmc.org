<?php

namespace CorvMC\Events\States;

use Spatie\ModelStates\StateConfig;
use CorvMC\Support\States\BaseState;
use CorvMC\Events\States\TicketState\{
    Pending,
    Valid,
    CheckedIn,
    Cancelled
};

abstract class TicketState extends BaseState
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->registerStatesFromDirectory(__DIR__ . '/TicketState')
            ->allowTransition(Pending::class, Valid::class)
            ->allowTransition(Pending::class, CheckedIn::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Valid::class, CheckedIn::class)
            ->allowTransition(Valid::class, Cancelled::class);
        // CheckedIn is terminal — never transitions to Cancelled (audit preservation)
    }
}
