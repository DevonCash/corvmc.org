<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class RevisionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\RevisionService::class;
    }
}