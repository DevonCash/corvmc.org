<?php

namespace CorvMC\Membership\Facades;

use Illuminate\Support\Facades\Facade;

class MemberProfileService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Membership\Services\MemberProfileService::class;
    }
}