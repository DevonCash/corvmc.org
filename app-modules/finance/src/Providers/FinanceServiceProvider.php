<?php

namespace CorvMC\Finance\Providers;

use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/finance.php',
            'finance'
        );
    }

    public function boot(): void
    {
    }
}
