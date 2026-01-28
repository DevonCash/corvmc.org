<?php

namespace CorvMC\Membership\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'membership');

        Blade::componentNamespace('CorvMC\\Membership\\View\\Components', 'membership');
    }
}
