<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class SubscriptionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\SubscriptionService::class;
    }
}