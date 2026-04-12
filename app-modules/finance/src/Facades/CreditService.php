<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class CreditService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\CreditService::class;
    }
}