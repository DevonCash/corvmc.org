<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class CacheService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\CacheService::class;
    }
}