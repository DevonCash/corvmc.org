<?php

namespace CorvMC\Support\Providers;

use CorvMC\Support\Models\RecurringSeries;
use CorvMC\Support\Policies\RecurringSeriesPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(RecurringSeries::class, RecurringSeriesPolicy::class);
    }
}
