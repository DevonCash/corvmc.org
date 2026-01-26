<?php

namespace CorvMC\Bands\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BandsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'bands');

        Blade::componentNamespace('CorvMC\\Bands\\View\\Components', 'bands');
    }
}
