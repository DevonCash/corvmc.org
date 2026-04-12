<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Services\InvitationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::cancel() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class CancelInvitation
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::cancel() instead
     */
    public function handle(Invitation $invitation): bool
    {
        try {
            return app(InvitationService::class)->cancel($invitation);
        } catch (\Exception $e) {
            return false;
        }
    }
}
