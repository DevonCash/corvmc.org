<?php

namespace App\Providers;

use App\Models\Production;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\ProductionObserver;
use App\Observers\ReservationObserver;
use App\Observers\TagObserver;
use App\Observers\TransactionObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Tags\Tag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\BandService::class);
        $this->app->singleton(\App\Services\ProductionService::class);
    }

    public function boot(): void
    {
        // Register model observers for cache invalidation
        User::observe(UserObserver::class);
        Reservation::observe(ReservationObserver::class);
        Production::observe(ProductionObserver::class);
        Transaction::observe(TransactionObserver::class);
        Tag::observe(TagObserver::class);

        // Automatically grant all abilities to admin users
        Gate::after(function ($user, $ability) {
            return $user->hasRole('admin');
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
