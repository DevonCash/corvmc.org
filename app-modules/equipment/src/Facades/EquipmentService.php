<?php

namespace CorvMC\Equipment\Facades;

use Illuminate\Support\Facades\Facade;

class EquipmentService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Equipment\Services\EquipmentService::class;
    }
}