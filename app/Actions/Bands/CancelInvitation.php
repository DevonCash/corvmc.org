<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelInvitation
{
    use AsAction;

    /**
     * Cancel a pending band invitation.
     */
    public function handle(Band $band, User $user): void
    {
        $membership = $band->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            throw BandException::userNotFound();
        }

        if ($membership->status !== 'invited') {
            throw BandException::invitationNotPending();
        }

        // Remove the invitation
        $membership->delete();
    }
}
