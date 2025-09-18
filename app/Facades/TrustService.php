<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class TrustService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\TrustService::class;
    }
}