<?php

namespace CorvMC\SpaceManagement\Http\Controllers;

use CorvMC\SpaceManagement\Services\UltraloqService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UltraloqOAuthController
{
    /**
     * Redirect to U-tec's OAuth authorization page.
     */
    public function redirect(UltraloqService $service)
    {
        $state = Str::random(40);
        session(['ultraloq_oauth_state' => $state]);

        $redirectUri = route('ultraloq.callback');

        return redirect($service->getAuthorizationUrl($redirectUri, $state));
    }

    /**
     * Handle the OAuth callback from U-tec.
     */
    public function callback(Request $request, UltraloqService $service)
    {
        $storedState = session()->pull('ultraloq_oauth_state');

        if (! $storedState || $request->query('state') !== $storedState) {
            Notification::make()
                ->title('OAuth Error')
                ->body('Invalid state parameter. Please try connecting again.')
                ->danger()
                ->sendToDatabase($request->user());

            return redirect('/staff/space-management');
        }

        $code = $request->query('authorization_code') ?? $request->query('code');

        if (! $code) {
            Notification::make()
                ->title('OAuth Error')
                ->body('No authorization code received from U-tec.')
                ->danger()
                ->sendToDatabase($request->user());

            return redirect('/staff/space-management');
        }

        $success = $service->exchangeCode($code);

        return redirect()->route('ultraloq.authed', [
            'success' => $success ? 1 : 0,
        ]);
    }

    /**
     * Show a simple confirmation page after OAuth completes.
     * This tab can be closed — the wizard in the main tab stays open.
     */
    public function authed(Request $request)
    {
        $success = (bool) $request->query('success', false);

        $title = $success ? 'U-tec Connected' : 'Connection Failed';
        $message = $success
            ? 'Your U-tec account is connected. You can close this tab and continue the lock setup wizard.'
            : 'Could not connect to U-tec. Close this tab, check your credentials, and try again.';
        $color = $success ? '#16a34a' : '#dc2626';

        return response(<<<HTML
            <!DOCTYPE html>
            <html>
            <head><title>{$title}</title></head>
            <body style="font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb;">
                <div style="text-align: center; max-width: 400px; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">{$this->statusIcon($success)}</div>
                    <h1 style="font-size: 1.25rem; color: {$color}; margin: 0 0 0.5rem;">{$title}</h1>
                    <p style="color: #6b7280; margin: 0;">{$message}</p>
                </div>
            </body>
            </html>
            HTML);
    }

    private function statusIcon(bool $success): string
    {
        return $success
            ? '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            : '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    }
}
