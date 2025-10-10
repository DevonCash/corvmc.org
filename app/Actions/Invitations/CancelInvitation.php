<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelInvitation
{
    use AsAction;

    /**
     * Cancel a pending invitation.
     */
    public function handle(Invitation $invitation): bool
    {
        // Only cancel if invitation hasn't been used
        if ($invitation->isUsed()) {
            return false;
        }

        return $invitation->delete();
    }
}
