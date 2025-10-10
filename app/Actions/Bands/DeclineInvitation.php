<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DeclineInvitation
{
    use AsAction;

    /**
     * Decline an invitation to join a band.
     */
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        $band->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);
    }
}
