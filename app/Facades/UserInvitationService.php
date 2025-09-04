<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class UserInvitationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\UserInvitationService::class;
    }
}