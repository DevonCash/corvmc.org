<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class UserSubscriptionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\UserSubscriptionService::class;
    }
}