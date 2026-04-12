<?php

namespace CorvMC\Events\Facades;

use Illuminate\Support\Facades\Facade;

class TicketService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CorvMC\Events\Services\TicketService::class;
    }
}