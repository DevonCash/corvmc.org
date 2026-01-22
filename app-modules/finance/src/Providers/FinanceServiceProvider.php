<?php

namespace CorvMC\Finance\Providers;

use CorvMC\Finance\Listeners\HandleChargeableCancelled;
use CorvMC\Finance\Listeners\HandleChargeableConfirmed;
use CorvMC\Finance\Listeners\HandleChargeableCreated;
use CorvMC\Finance\Listeners\HandleChargeableUpdated;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
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
    }

    public function boot(): void
    {
        $this->registerEventListeners();
    }

    protected function registerEventListeners(): void
    {
        // SpaceManagement reservation events → Finance charge/credit handling
        Event::listen(ReservationCreated::class, HandleChargeableCreated::class);
        Event::listen(ReservationCancelled::class, HandleChargeableCancelled::class);
        Event::listen(ReservationUpdated::class, HandleChargeableUpdated::class);
        Event::listen(ReservationConfirmed::class, HandleChargeableConfirmed::class);
    }
}
