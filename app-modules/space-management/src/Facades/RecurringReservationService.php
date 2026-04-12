<?php

namespace CorvMC\SpaceManagement\Facades;

use Illuminate\Support\Facades\Facade;

class RecurringReservationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\SpaceManagement\Services\RecurringReservationService::class;
    }
}