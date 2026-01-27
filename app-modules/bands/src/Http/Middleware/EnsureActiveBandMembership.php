<?php

namespace CorvMC\Bands\Http\Middleware;

use Closure;
use CorvMC\Bands\Models\Band;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBandMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Band|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return redirect()->route('filament.member.pages.member-dashboard');
        }

        // Verify user has active membership (not just invited)
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('filament.member.pages.member-dashboard');
        }

        // Check for active membership (owner or active member)
        $isActiveMember = $tenant->owner_id === $user->id
            || $tenant->activeMembers()->where('user_id', $user->id)->exists();

        if (! $isActiveMember) {
            // Check if user has a pending invitation to this band
            $hasInvitation = $tenant->memberships()
                ->invited()
                ->where('user_id', $user->id)
                ->exists();

            if ($hasInvitation) {
                return redirect("/band/{$tenant->slug}/accept-invitation");
            }

            Notification::make()
                ->warning()
                ->title('Access Denied')
                ->body('You are not a member of this band.')
                ->send();

            return redirect()->route('filament.member.pages.member-dashboard');
        }

        return $next($request);
    }
}
