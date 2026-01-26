<?php

namespace CorvMC\Events\Providers;

use CorvMC\Events\Models\Event;
use CorvMC\Events\Policies\EventPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Event::class, EventPolicy::class);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'events');

        Blade::componentNamespace('CorvMC\\Events\\View\\Components', 'events');
    }
}
