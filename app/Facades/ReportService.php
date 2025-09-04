<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class ReportService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\ReportService::class;
    }
}