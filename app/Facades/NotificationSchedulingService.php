<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class NotificationSchedulingService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\NotificationSchedulingService::class;
    }
}