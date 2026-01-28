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
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'space-management');

        Blade::componentNamespace('CorvMC\\SpaceManagement\\View\\Components', 'space-management');
    }
}
