<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class ProductionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\ProductionService::class;
    }
}