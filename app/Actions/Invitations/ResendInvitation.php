<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Services\InvitationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::resend() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class ResendInvitation
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::resend() instead
     */
    public function handle(string $email): Invitation
    {
        // Find the most recent invitation for this email
        $invitation = Invitation::withoutGlobalScopes()
            ->where('email', $email)
            ->whereNull('used_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$invitation) {
            throw new \Exception('No pending invitation found for this email.');
        }

        return app(InvitationService::class)->resend($invitation);
    }
}
