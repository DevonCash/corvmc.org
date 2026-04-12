<?php

namespace App\Actions\Invitations;

use App\Models\User;
use App\Services\InvitationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::accept() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class AcceptInvitation
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::accept() instead
     */
    public function handle(string $token, array $userData): ?User
    {
        $invitation = app(InvitationService::class)->findByToken($token);
        
        if (!$invitation) {
            return null;
        }

        try {
            return app(InvitationService::class)->accept($invitation, $userData);
        } catch (\Exception $e) {
            return null;
        }
    }
}
