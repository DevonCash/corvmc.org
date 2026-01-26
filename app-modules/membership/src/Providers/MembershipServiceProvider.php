<?php

namespace CorvMC\Membership\Providers;

use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Membership\Policies\MemberProfilePolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(MemberProfile::class, MemberProfilePolicy::class);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'membership');

        Blade::componentNamespace('CorvMC\\Membership\\View\\Components', 'membership');
    }
}
