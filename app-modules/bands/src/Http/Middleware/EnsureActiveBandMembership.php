<?php

namespace CorvMC\Bands\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBandMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return redirect()->route('filament.member.pages.member-dashboard');
        }

        // Verify user still has active membership
        $user = auth()->user();
        if (! $user || ! $user->canAccessTenant($tenant)) {
            Notification::make()
                ->warning()
                ->title('Access Denied')
                ->body('You no longer have access to this band.')
                ->send();

            return redirect()->route('filament.member.pages.member-dashboard');
        }

        return $next($request);
    }
}
