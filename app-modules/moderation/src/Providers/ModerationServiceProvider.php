<?php

namespace CorvMC\Moderation\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'moderation');

        Blade::componentNamespace('CorvMC\\Moderation\\View\\Components', 'moderation');
    }
}
