<?php

namespace CorvMC\Moderation\Facades;

use Illuminate\Support\Facades\Facade;

class ReportService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Moderation\Services\ReportService::class;
    }
}