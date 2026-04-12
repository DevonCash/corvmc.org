<?php

namespace CorvMC\Sponsorship\Facades;

use Illuminate\Support\Facades\Facade;

class SponsorshipService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Sponsorship\Services\SponsorshipService::class;
    }
}