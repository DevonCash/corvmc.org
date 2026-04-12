<?php

namespace CorvMC\Moderation\Facades;

use Illuminate\Support\Facades\Facade;

class RevisionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Moderation\Services\RevisionService::class;
    }
}