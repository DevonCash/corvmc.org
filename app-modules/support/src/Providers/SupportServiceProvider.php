<?php

namespace CorvMC\Support\Providers;

use CorvMC\Support\Services\RecurringService;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecurringService::class);
    }

    public function boot(): void
    {
    }
}
