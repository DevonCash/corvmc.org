<?php

namespace App\Actions\Invitations;

use App\Models\User;
use App\Services\InvitationService;
use CorvMC\Bands\Models\Band;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::confirmBandOwnership() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class ConfirmBandOwnership
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::confirmBandOwnership() instead
     */
    public function handle(User $user, Band $band): bool
    {
        return app(InvitationService::class)->confirmBandOwnership($user, $band);
    }
}
