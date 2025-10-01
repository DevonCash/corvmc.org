<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class CreditService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\CreditService::class;
    }
}
