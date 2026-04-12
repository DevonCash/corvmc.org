<?php

namespace CorvMC\SpaceManagement\Providers;

use CorvMC\SpaceManagement\Contracts\ConflictCheckerInterface;
use CorvMC\SpaceManagement\Services\ConflictChecker;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class SpaceManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConflictCheckerInterface::class, ConflictChecker::class);

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
