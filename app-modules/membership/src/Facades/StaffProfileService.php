<?php

namespace CorvMC\Membership\Facades;

use Illuminate\Support\Facades\Facade;

class StaffProfileService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Membership\Services\StaffProfileService::class;
    }
}