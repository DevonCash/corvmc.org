<?php

namespace App\Providers;

use App\Models\Production;
use App\Models\Reservation;
use App\Models\Subscription;
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
        $this->app->singleton(\App\Services\CacheService::class);
        $this->app->singleton(\App\Services\CalendarService::class);
        $this->app->singleton(\App\Services\GitHubService::class);
        $this->app->singleton(\App\Services\MemberBenefitsService::class);
        $this->app->singleton(\App\Services\MemberProfileService::class);
        $this->app->singleton(\App\Services\NotificationSchedulingService::class);
        $this->app->singleton(\App\Services\ProductionService::class);
        $this->app->singleton(\App\Services\ReportService::class);
        $this->app->singleton(\App\Services\ReservationService::class);
        $this->app->singleton(\App\Services\PaymentService::class);
        $this->app->singleton(\App\Services\UserInvitationService::class);
        $this->app->singleton(\App\Services\UserSubscriptionService::class);
        $this->app->singleton(\App\Services\UserService::class);
        $this->app->singleton(\App\Services\StaffProfileService::class);
    }

    public function boot(): void
    {
        // Register custom Cashier models
        Cashier::useSubscriptionModel(Subscription::class);

        // Register model observers for cache invalidation
        User::observe(UserObserver::class);
        Reservation::observe(ReservationObserver::class);
        Production::observe(ProductionObserver::class);
        Transaction::observe(TransactionObserver::class);
        Tag::observe(TagObserver::class);

        // Register facade aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('BandService', \App\Facades\BandService::class);
        $loader->alias('CacheService', \App\Facades\CacheService::class);
        $loader->alias('CalendarService', \App\Facades\CalendarService::class);
        $loader->alias('GitHubService', \App\Facades\GitHubService::class);
        $loader->alias('MemberBenefitsService', \App\Facades\MemberBenefitsService::class);
        $loader->alias('MemberProfileService', \App\Facades\MemberProfileService::class);
        $loader->alias('NotificationSchedulingService', \App\Facades\NotificationSchedulingService::class);
        $loader->alias('ProductionService', \App\Facades\ProductionService::class);
        $loader->alias('ReportService', \App\Facades\ReportService::class);
        $loader->alias('ReservationService', \App\Facades\ReservationService::class);
        $loader->alias('PaymentService', \App\Facades\PaymentService::class);
        $loader->alias('UserInvitationService', \App\Facades\UserInvitationService::class);
        $loader->alias('UserSubscriptionService', \App\Facades\UserSubscriptionService::class);
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
