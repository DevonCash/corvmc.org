<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class BandService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\BandService::class;
    }
}