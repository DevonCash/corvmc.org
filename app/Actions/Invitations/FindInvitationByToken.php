<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Lorisleiva\Actions\Concerns\AsAction;

class FindInvitationByToken
{
    use AsAction;

    /**
     * Find invitation by token.
     */
    public function handle(string $token): ?Invitation
    {
        return Invitation::withoutGlobalScopes()->where('token', $token)->first();
    }
}
