<?php

namespace CorvMC\Moderation\Facades;

use Illuminate\Support\Facades\Facade;

class TrustService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Moderation\Services\TrustService::class;
    }
}