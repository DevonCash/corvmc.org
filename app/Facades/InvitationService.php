<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class InvitationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\InvitationService::class;
    }
}