<?php

namespace CorvMC\Membership\Providers;

use CorvMC\Membership\Services\BandService;
use CorvMC\Membership\Services\MemberProfileService;
use CorvMC\Membership\Services\StaffProfileService;
use CorvMC\Membership\Services\UserManagementService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MemberProfileService::class);
        $this->app->singleton(BandService::class);
        $this->app->singleton(UserManagementService::class);
        $this->app->singleton(StaffProfileService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'membership');

        Blade::componentNamespace('CorvMC\\Membership\\View\\Components', 'membership');
    }
}
