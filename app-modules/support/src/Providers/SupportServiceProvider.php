<?php

namespace CorvMC\Support\Providers;

use CorvMC\Support\Services\InvitationService;
use CorvMC\Support\Services\RecurringService;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecurringService::class);
        $this->app->singleton(InvitationService::class);
    }

    public function boot(): void
    {
    }
}
