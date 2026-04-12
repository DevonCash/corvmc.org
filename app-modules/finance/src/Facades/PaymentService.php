<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class PaymentService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\PaymentService::class;
    }
}