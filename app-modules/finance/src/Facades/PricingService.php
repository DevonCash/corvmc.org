<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class PricingService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\PricingService::class;
    }
}