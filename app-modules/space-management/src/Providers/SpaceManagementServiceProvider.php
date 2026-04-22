<?php

namespace CorvMC\SpaceManagement\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class SpaceManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register SpaceManagement services as singletons
        $this->app->singleton(\CorvMC\SpaceManagement\Services\RecurringReservationService::class);
        $this->app->singleton(\CorvMC\SpaceManagement\Services\ReservationService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'space-management');

        Blade::componentNamespace('CorvMC\\SpaceManagement\\View\\Components', 'space-management');
    }
}
