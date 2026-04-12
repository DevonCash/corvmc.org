<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class FeeService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\FeeService::class;
    }
}