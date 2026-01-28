<?php

namespace CorvMC\Equipment\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EquipmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'equipment');

        Blade::componentNamespace('CorvMC\\Equipment\\View\\Components', 'equipment');
    }
}
