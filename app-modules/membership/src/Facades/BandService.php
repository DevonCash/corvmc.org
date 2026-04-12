<?php

namespace CorvMC\Membership\Facades;

use Illuminate\Support\Facades\Facade;

class BandService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Membership\Services\BandService::class;
    }
}