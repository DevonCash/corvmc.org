<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class GitHubService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\GitHubService::class;
    }
}
