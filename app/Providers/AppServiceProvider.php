<?php

namespace App\Providers;

use App\Models\Production;
use App\Models\Reservation;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\ProductionObserver;
use App\Observers\ReservationObserver;
use App\Observers\TagObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Spatie\Tags\Tag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\BandService::class);
        $this->app->singleton(\App\Services\GitHubService::class);
        $this->app->singleton(\App\Services\MemberProfileService::class);
        $this->app->singleton(\App\Services\NotificationSchedulingService::class);
        $this->app->singleton(\App\Services\ProductionService::class);
        $this->app->singleton(\App\Services\UserService::class);
        $this->app->singleton(\App\Services\StaffProfileService::class);
    }

    public function boot(): void
    {
        // Register custom Cashier models
        Cashier::useSubscriptionModel(Subscription::class);

        // Register model observers for cache invalidation
        User::observe(UserObserver::class);
        \App\Models\RehearsalReservation::observe(ReservationObserver::class);
        \App\Models\ProductionReservation::observe(ReservationObserver::class);
        Production::observe(ProductionObserver::class);
        Tag::observe(TagObserver::class);

        // Register facade aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('BandService', \App\Facades\BandService::class);
        $loader->alias('GitHubService', \App\Facades\GitHubService::class);
        $loader->alias('MemberProfileService', \App\Facades\MemberProfileService::class);
        $loader->alias('NotificationSchedulingService', \App\Facades\NotificationSchedulingService::class);
        $loader->alias('ProductionService', \App\Facades\ProductionService::class);
        $loader->alias('UserService', \App\Facades\UserService::class);
        $loader->alias('StaffProfileService', \App\Facades\StaffProfileService::class);

        // Automatically grant all abilities to admin users
        Gate::after(function ($user, $ability) {
            return $user->hasRole('admin');
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
