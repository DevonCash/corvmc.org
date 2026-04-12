<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class EventSyncService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\EventSyncService::class;
    }
}