<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUserWithBand
{
    use AsAction;

    /**
     * Invite a user and attach band data to the invitation.
     */
    public function handle(string $email, string $bandName, array $bandData = []): Invitation
    {
        return InviteUser::run($email, [
            'band_name' => $bandName,
            'band_data' => $bandData,
        ]);
    }
}
