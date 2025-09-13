<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class CalendarService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\CalendarService::class;
    }
}