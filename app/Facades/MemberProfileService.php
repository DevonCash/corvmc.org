<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class MemberProfileService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\MemberProfileService::class;
    }
}