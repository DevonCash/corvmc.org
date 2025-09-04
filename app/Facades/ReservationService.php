<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class ReservationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\ReservationService::class;
    }
}