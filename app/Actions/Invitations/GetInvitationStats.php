<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Lorisleiva\Actions\Concerns\AsAction;

class GetInvitationStats
{
    use AsAction;

    /**
     * Get invitation statistics.
     */
    public function handle(): array
    {
        $totalInvitations = Invitation::withoutGlobalScopes()->count();
        $pendingInvitations = Invitation::withoutGlobalScopes()->whereNull('used_at')->count();
        $acceptedInvitations = Invitation::withoutGlobalScopes()->whereNotNull('used_at')->count();
        $expiredInvitations = Invitation::withoutGlobalScopes()
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->count();

        return [
            'total_invitations' => $totalInvitations,
            'pending_invitations' => $pendingInvitations,
            'accepted_invitations' => $acceptedInvitations,
            'expired_invitations' => $expiredInvitations,
            'acceptance_rate' => $totalInvitations > 0 ? ($acceptedInvitations / $totalInvitations) * 100 : 0,
            'pending_active' => $pendingInvitations - $expiredInvitations,
        ];
    }
}
