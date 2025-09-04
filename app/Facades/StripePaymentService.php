<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class StripePaymentService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\StripePaymentService::class;
    }
}