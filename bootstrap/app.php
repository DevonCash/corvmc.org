<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Exclude Stripe webhook from CSRF verification
        $middleware->validateCsrfTokens(except: [
            '/stripe/*',
        ]);

        if(env('APP_ENV') !== 'local') {
            $middleware->replace(
                \Illuminate\Http\Middleware\TrustProxies::class,
                \Monicahq\Cloudflare\Http\Middleware\TrustProxies::class
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
