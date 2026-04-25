<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add signed middleware alias for Filament password reset
        $middleware->alias([
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        ]);

        // Exclude Stripe webhook from CSRF verification
        $middleware->validateCsrfTokens(except: [
            '/stripe/*',
        ]);

        if (env('APP_ENV') !== 'local') {
            $middleware->replace(
                \Illuminate\Http\Middleware\TrustProxies::class,
                \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Enrich Sentry errors with the authenticated user
        $exceptions->report(function (Throwable $e) {
            try {
                if (auth()->check()) {
                    \Sentry\configureScope(function (Scope $scope): void {
                        $user = auth()->user();
                        $scope->setUser([
                            'id' => $user->id,
                            'email' => $user->email,
                            'username' => $user->name,
                        ]);
                    });
                }
            } catch (Throwable) {
                // Auth guard may not be available during early bootstrap errors
            }
        });

        // Filament's built-in error notifications handle Livewire errors
        // automatically — they intercept failed requests and show a toast
        // notification instead of the default error modal.
    })->create();
