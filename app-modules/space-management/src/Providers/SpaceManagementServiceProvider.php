<?php

namespace CorvMC\SpaceManagement\Providers;

use CorvMC\SpaceManagement\Contracts\ConflictCheckerInterface;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Policies\ReservationPolicy;
use CorvMC\SpaceManagement\Services\ConflictChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SpaceManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConflictCheckerInterface::class, ConflictChecker::class);
    }

    public function boot(): void
    {
        Gate::policy(Reservation::class, ReservationPolicy::class);
    }
}
