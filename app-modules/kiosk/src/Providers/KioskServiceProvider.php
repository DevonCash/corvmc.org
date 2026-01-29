<?php

namespace CorvMC\Kiosk\Providers;

use CorvMC\Kiosk\Http\Middleware\EnsureKioskDevice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KioskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Register kiosk.device middleware alias
        $this->app['router']->aliasMiddleware('kiosk.device', EnsureKioskDevice::class);

        // Register API routes
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../../routes/api.php');
    }
}
