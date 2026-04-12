<?php

namespace CorvMC\Events\Providers;

use CorvMC\Events\Services\EventService;
use CorvMC\Events\Services\TicketService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventService::class);
        $this->app->singleton(TicketService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'events');

        Blade::componentNamespace('CorvMC\\Events\\View\\Components', 'events');
    }
}
