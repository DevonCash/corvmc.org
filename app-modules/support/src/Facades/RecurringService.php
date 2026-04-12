<?php

namespace CorvMC\Support\Facades;

use Illuminate\Support\Facades\Facade;

class RecurringService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Support\Services\RecurringService::class;
    }
}