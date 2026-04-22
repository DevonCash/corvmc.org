<?php

namespace CorvMC\Finance\States;

use Spatie\ModelStates\StateConfig;
use CorvMC\Support\States\BaseState;
use CorvMC\Finance\States\TransactionState\{
    Pending,
    Cleared,
    Cancelled,
    Failed
};

abstract class TransactionState extends BaseState
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->registerStatesFromDirectory(__DIR__ . '/TransactionState')
            ->allowTransition(Pending::class, Cleared::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Pending::class, Failed::class);
    }
}
