<?php

namespace CorvMC\SpaceManagement\Facades;

use Illuminate\Support\Facades\Facade;

class ReservationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\SpaceManagement\Services\ReservationService::class;
    }
}