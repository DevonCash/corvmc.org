<?php

namespace CorvMC\Support\Facades;

use Illuminate\Support\Facades\Facade;

class InvitationService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Support\Services\InvitationService::class;
    }
}
