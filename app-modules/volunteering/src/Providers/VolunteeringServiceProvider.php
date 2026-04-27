<?php

namespace CorvMC\Volunteering\Providers;

use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\Services\PositionService;
use CorvMC\Volunteering\Services\ShiftService;
use Illuminate\Support\ServiceProvider;

class VolunteeringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PositionService::class);
        $this->app->singleton(ShiftService::class);
        $this->app->singleton(HourLogService::class);
    }

    public function boot(): void
    {
        //
    }
}
