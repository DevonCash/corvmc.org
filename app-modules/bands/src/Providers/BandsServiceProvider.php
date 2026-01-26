<?php

namespace CorvMC\Bands\Providers;

use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Bands\Policies\BandMemberPolicy;
use CorvMC\Bands\Policies\BandPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BandsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Band::class, BandPolicy::class);
        Gate::policy(BandMember::class, BandMemberPolicy::class);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'bands');

        Blade::componentNamespace('CorvMC\\Bands\\View\\Components', 'bands');
    }
}
