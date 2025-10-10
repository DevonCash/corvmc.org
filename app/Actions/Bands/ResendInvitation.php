<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class ResendInvitation
{
    use AsAction;

    /**
     * Resend an invitation to a user.
     */
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->for($user)->exists()) {
            throw BandException::userNotInvited();
        }

        // Update the invited_at timestamp
        $band->members()->updateExistingPivot($user->id, [
            'invited_at' => now(),
        ]);

        // Get the current invitation details
        $member = $band->members()->wherePivot('user_id', $user->id)->first();

        // Resend notification
        $user->notify(new BandInvitationNotification(
            $band,
            $member->pivot->role,
            $member->pivot->position
        ));
    }
}
