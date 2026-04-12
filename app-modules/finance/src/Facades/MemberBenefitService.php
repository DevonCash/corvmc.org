<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class MemberBenefitService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Finance\Services\MemberBenefitService::class;
    }
}