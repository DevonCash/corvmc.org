<?php

namespace CorvMC\Events\Facades;

use Illuminate\Support\Facades\Facade;

class EventService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Events\Services\EventService::class;
    }
}