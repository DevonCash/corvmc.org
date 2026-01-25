<?php

namespace App\Providers;

use App\Listeners\CheckEventSpaceConflicts;
use App\Livewire\Synthesizers\MoneySynthesizer;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\ReservationObserver;
use App\Observers\TagObserver;
use App\Observers\UserObserver;
use BezhanSalleh\PanelSwitch\PanelSwitch;
use CorvMC\Events\Events\EventScheduling;
use CorvMC\Finance\Listeners\HandleChargeableCancelled;
use CorvMC\Finance\Listeners\HandleChargeableConfirmed;
use CorvMC\Finance\Listeners\HandleChargeableCreated;
use CorvMC\Finance\Listeners\HandleChargeableUpdated;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use Spatie\Tags\Tag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GitHubService::class);
    }

    public function boot(): void
    {
        // Register event listeners for cross-module integration
        Event::listen(EventScheduling::class, CheckEventSpaceConflicts::class);

        // SpaceManagement → Finance integration (reservation pricing/charges)
        Event::listen(ReservationCreated::class, HandleChargeableCreated::class);
        Event::listen(ReservationCancelled::class, HandleChargeableCancelled::class);
        Event::listen(ReservationUpdated::class, HandleChargeableUpdated::class);
        Event::listen(ReservationConfirmed::class, HandleChargeableConfirmed::class);

        FilamentTimezone::set(config('app.timezone'));
        PanelSwitch::configureUsing(function (PanelSwitch $panelSwitch) {
            // Custom configurations go here
            $panelSwitch
                ->icons([
                    'member' => 'tabler-user',
                    'staff' => 'tabler-user-shield',
                ], asImage: false)
                ->simple();
        });

        // Register custom Cashier models
        Cashier::useSubscriptionModel(Subscription::class);

        // Register Livewire synthesizers
        Livewire::propertySynthesizer(MoneySynthesizer::class);

        // Register model observers for cache invalidation
        User::observe(UserObserver::class);
        \CorvMC\SpaceManagement\Models\RehearsalReservation::observe(ReservationObserver::class);
        \App\Models\EventReservation::observe(ReservationObserver::class);
        \CorvMC\Events\Models\Event::observe(\App\Observers\EventObserver::class);
        Tag::observe(TagObserver::class);

        // Register facade aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('GitHubService', \App\Facades\GitHubService::class);

        // Automatically grant all abilities to admin users
        Gate::after(function ($user, $ability) {
            return $user->hasRole('admin');
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
