<?php

namespace CorvMC\Membership\Facades;

use Illuminate\Support\Facades\Facade;

class UserManagementService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Membership\Services\UserManagementService::class;
    }
}