<?php

namespace CorvMC\Events\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'events');

        Blade::componentNamespace('CorvMC\\Events\\View\\Components', 'events');
    }
}
