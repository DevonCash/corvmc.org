<?php

namespace CorvMC\SpaceManagement\Providers;

use CorvMC\SpaceManagement\Console\SendRehearsalRemindersCommand;
use CorvMC\SpaceManagement\Http\Controllers\UltraloqOAuthController;
use CorvMC\SpaceManagement\Listeners\SendRehearsalAttendanceNotification;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\SpaceManagement\Services\ReservationService;
use CorvMC\SpaceManagement\Services\UltraloqService;
use CorvMC\Support\Events\InvitationCreated;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SpaceManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register SpaceManagement services as singletons
        $this->app->singleton(RecurringReservationService::class);
        $this->app->singleton(ReservationService::class);
        $this->app->singleton(UltraloqService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'space-management');

        Blade::componentNamespace('CorvMC\\SpaceManagement\\View\\Components', 'space-management');

        Event::listen(InvitationCreated::class, SendRehearsalAttendanceNotification::class);

        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/ultraloq/authorize', [UltraloqOAuthController::class, 'redirect'])
                ->name('ultraloq.authorize');
            Route::get('/ultraloq/callback', [UltraloqOAuthController::class, 'callback'])
                ->name('ultraloq.callback');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendRehearsalRemindersCommand::class,
            ]);
        }
    }
}
