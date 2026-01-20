<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

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
        // ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        // Custom rendering for Livewire requests
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->header('X-Livewire')) {
                try {
                    report($e);
                } catch (Throwable $reportException) {
                    // Silently fail if reporting fails to prevent infinite loop
                }

                // In development, show the default error modal with full details
                if (! app()->environment('production')) {
                    return null;
                }

                // In production, show a user-friendly message
                \Filament\Notifications\Notification::make()
                    ->title('An error occurred')
                    ->body('Something went wrong. Our team has been notified.')
                    ->danger()
                    ->send();

                // Return HTML that Livewire can display in its error modal
                return response(
                    '<div style="padding: 2rem; text-align: center;">
                        <h2 style="margin-bottom: 1rem;">Something went wrong</h2>
                        <p>Our team has been notified. Please try again.</p>
                    </div>',
                    500
                );
            }
        });
    })->create();
