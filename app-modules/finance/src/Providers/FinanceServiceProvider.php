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

        // Register FinanceManager (backing the Finance facade)
        $this->app->singleton(\CorvMC\Finance\FinanceManager::class);

        // Register Finance services as singletons
        $this->app->singleton(\CorvMC\Finance\Services\PaymentService::class);
        $this->app->singleton(\CorvMC\Finance\Services\CreditService::class);
        $this->app->singleton(\CorvMC\Finance\Services\SubscriptionService::class);
        $this->app->singleton(\CorvMC\Finance\Services\FeeService::class);
        $this->app->singleton(\CorvMC\Finance\Services\MemberBenefitService::class);
        $this->app->singleton(\CorvMC\Finance\Services\PricingService::class);
    }

    public function boot(): void
    {
    }
}
