<?php

namespace CorvMC\Moderation\Facades;

use Illuminate\Support\Facades\Facade;

class SpamPreventionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Moderation\Services\SpamPreventionService::class;
    }
}