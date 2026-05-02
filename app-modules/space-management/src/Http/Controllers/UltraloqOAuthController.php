<?php

namespace CorvMC\SpaceManagement\Http\Controllers;

use CorvMC\SpaceManagement\Services\UltraloqService;
use Filament\Notifications\Notification;
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

            return redirect('/Staff/space-management');
        }

        $code = $request->query('authorization_code') ?? $request->query('code');

        if (! $code) {
            Notification::make()
                ->title('OAuth Error')
                ->body('No authorization code received from U-tec.')
                ->danger()
                ->sendToDatabase($request->user());

            return redirect('/Staff/space-management');
        }

        $success = $service->exchangeCode($code);

        if ($success) {
            Notification::make()
                ->title('U-tec Connected')
                ->body('Successfully connected to your U-tec account. Now select your lock device.')
                ->success()
                ->sendToDatabase($request->user());
        } else {
            Notification::make()
                ->title('Connection Failed')
                ->body('Could not connect to U-tec. Please check your credentials and try again.')
                ->danger()
                ->sendToDatabase($request->user());
        }

        return redirect('/Staff/space-management');
    }
}
