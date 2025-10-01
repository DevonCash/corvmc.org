<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class RecurringReservationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\RecurringReservationService::class;
    }
}
