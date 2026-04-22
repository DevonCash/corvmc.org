<?php

namespace CorvMC\Finance\States;

use Spatie\ModelStates\StateConfig;
use CorvMC\Support\States\BaseState;
use CorvMC\Finance\States\OrderState\{
    Pending,
    Completed,
    Comped,
    Refunded,
    Cancelled
};

abstract class OrderState extends BaseState
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->registerStatesFromDirectory(__DIR__ . '/OrderState')
            ->allowTransition(Pending::class, Completed::class)
            ->allowTransition(Pending::class, Comped::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Completed::class, Refunded::class)
            ->allowTransition(Comped::class, Refunded::class);
    }
}
