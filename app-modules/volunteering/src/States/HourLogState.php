<?php

namespace CorvMC\Volunteering\States;

use CorvMC\Support\States\BaseState;
use CorvMC\Volunteering\States\HourLogState\Approved;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Pending;
use CorvMC\Volunteering\States\HourLogState\Rejected;
use CorvMC\Volunteering\States\HourLogState\Released;
use Spatie\ModelStates\StateConfig;

abstract class HourLogState extends BaseState
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Interested::class)
            ->registerStatesFromDirectory(__DIR__.'/HourLogState')

            // Shift lifecycle
            ->allowTransition(Interested::class, Confirmed::class)
            ->allowTransition(Interested::class, Released::class)
            ->allowTransition(Confirmed::class, CheckedIn::class)
            ->allowTransition(Confirmed::class, Released::class)
            ->allowTransition(CheckedIn::class, CheckedOut::class)
            ->allowTransition(CheckedIn::class, Released::class)

            // Self-reported lifecycle
            ->allowTransition(Pending::class, Approved::class)
            ->allowTransition(Pending::class, Rejected::class);
    }
}
