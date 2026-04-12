<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Services\InvitationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::findByToken() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class FindInvitationByToken
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::findByToken() instead
     */
    public function handle(string $token): ?Invitation
    {
        return app(InvitationService::class)->findByToken($token);
    }
}
