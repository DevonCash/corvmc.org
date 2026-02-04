<?php

namespace App\Providers;

use App\Listeners\CheckEventSpaceConflicts;
use App\Listeners\LogReservationActivity;
use App\Livewire\Synthesizers\MoneySynthesizer;
use CorvMC\Finance\Models\Subscription;
use App\Models\User;
use App\Observers\ReservationObserver;
use App\Observers\SpaceClosureObserver;
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
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Configure morph map to decouple polymorphic type strings from class names
        // This allows models to be moved between namespaces without database migrations
        Relation::enforceMorphMap([
            // Core models
            'user' => \App\Models\User::class,
            'staff_profile' => \App\Models\StaffProfile::class,
            'invitation' => \App\Models\Invitation::class,

            // Band module models
            'band' => \CorvMC\Bands\Models\Band::class,
            'band_member' => \CorvMC\Bands\Models\BandMember::class,

            // Membership module models
            'member_profile' => \CorvMC\Membership\Models\MemberProfile::class,

            // Event module models
            'event' => \CorvMC\Events\Models\Event::class,
            'venue' => \CorvMC\Events\Models\Venue::class,
            'event_reservation' => \App\Models\EventReservation::class,
            'ticket_order' => \CorvMC\Events\Models\TicketOrder::class,
            'ticket' => \CorvMC\Events\Models\Ticket::class,

            // Space management models
            'reservation' => \CorvMC\SpaceManagement\Models\Reservation::class,
            'rehearsal_reservation' => \CorvMC\SpaceManagement\Models\RehearsalReservation::class,
            'recurring_series' => \CorvMC\Support\Models\RecurringSeries::class,

            // Equipment models
            'equipment' => \CorvMC\Equipment\Models\Equipment::class,
            'equipment_loan' => \CorvMC\Equipment\Models\EquipmentLoan::class,
            'equipment_damage_report' => \CorvMC\Equipment\Models\EquipmentDamageReport::class,

            // Finance models
            'charge' => \CorvMC\Finance\Models\Charge::class,
            'subscription' => \CorvMC\Finance\Models\Subscription::class,

            // Moderation models
            'report' => \CorvMC\Moderation\Models\Report::class,
            'revision' => \CorvMC\Moderation\Models\Revision::class,

            // Sponsorship models
            'sponsor' => \CorvMC\Sponsorship\Models\Sponsor::class,

            // Local Resources models
            'resource_list' => \App\Models\ResourceList::class,
            'local_resource' => \App\Models\LocalResource::class,
        ]);

        // Register event listeners for cross-module integration
        Event::listen(EventScheduling::class, CheckEventSpaceConflicts::class);

        // SpaceManagement → Finance integration (reservation pricing/charges)
        Event::listen(ReservationCreated::class, HandleChargeableCreated::class);
        Event::listen(ReservationCancelled::class, HandleChargeableCancelled::class);
        Event::listen(ReservationUpdated::class, HandleChargeableUpdated::class);
        Event::listen(ReservationConfirmed::class, HandleChargeableConfirmed::class);

        // SpaceManagement → Activity logging (reservation lifecycle)
        Event::listen(ReservationCreated::class, [LogReservationActivity::class, 'handleCreated']);
        Event::listen(ReservationConfirmed::class, [LogReservationActivity::class, 'handleConfirmed']);
        Event::listen(ReservationCancelled::class, [LogReservationActivity::class, 'handleCancelled']);
        Event::listen(ReservationUpdated::class, [LogReservationActivity::class, 'handleUpdated']);

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
        \CorvMC\SpaceManagement\Models\SpaceClosure::observe(SpaceClosureObserver::class);
        Tag::observe(TagObserver::class);

        // Register facade aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('GitHubService', \App\Facades\GitHubService::class);

        // Enable policy auto-discovery for module models
        // Converts CorvMC\{Module}\Models\{Model} -> App\Policies\{Model}Policy
        Gate::guessPolicyNamesUsing(function (string $modelClass): ?string {
            // Extract just the class name (e.g., "RehearsalReservation" from "CorvMC\SpaceManagement\Models\RehearsalReservation")
            $modelName = class_basename($modelClass);
            $policyClass = "App\\Policies\\{$modelName}Policy";

            return class_exists($policyClass) ? $policyClass : null;
        });

        // Automatically grant all abilities to admin users
        Gate::after(function ($user, $ability) {
            return $user->hasRole('admin');
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
