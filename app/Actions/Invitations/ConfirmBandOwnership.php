<?php

namespace App\Actions\Invitations;

use App\Facades\BandService;
use App\Models\Invitation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Role;

class ConfirmBandOwnership
{
    use AsAction;

    /**
     * Confirm band ownership when user completes their invitation.
     */
    public function handle(User $user, Invitation $invitation): void
    {
        if (!isset($invitation->data['band_id'])) {
            return;
        }

        // Use BandService to handle band ownership confirmation
        BandService::confirmBandOwnershipFromInvitation($user, $invitation->data);

        // Assign any roles specified in the invitation
        if (!empty($invitation->data['roles'])) {
            $roles = Role::whereIn('name', $invitation->data['roles'])->get();
            $user->syncRoles($roles);
        }
    }
}
