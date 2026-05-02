<?php

namespace CorvMC\Finance\Providers;

use CorvMC\Finance\Events\TransactionCleared;
use CorvMC\Finance\Listeners\CancelOrderOnReservationCancelled;
use CorvMC\Finance\Listeners\CheckOrderSettlement;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(\CorvMC\Finance\Services\CreditService::class);
        $this->app->singleton(\CorvMC\Finance\Services\SubscriptionService::class);
        $this->app->singleton(\CorvMC\Finance\Services\FeeService::class);
        $this->app->singleton(\CorvMC\Finance\Services\MemberBenefitService::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionCleared::class, CheckOrderSettlement::class);
        Event::listen(ReservationCancelled::class, CancelOrderOnReservationCancelled::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \CorvMC\Finance\Console\Commands\SweepStaleTransactions::class,
                \CorvMC\Finance\Console\Commands\ReconcileTransactions::class,
            ]);
        }
    }
}
