<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class StaffProfileService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\StaffProfileService::class;
    }
}