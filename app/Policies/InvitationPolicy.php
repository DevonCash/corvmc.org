<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Support\Models\Invitation;

class InvitationPolicy
{
    /**
     * Any authenticated user can view invitations (filtered by query scope).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Users can view invitations they sent or received.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        return $user->id === $invitation->user_id
            || $user->id === $invitation->inviter_id;
    }

    /**
     * The invitee can respond (accept or decline) to a pending invitation.
     */
    public function respond(User $user, Invitation $invitation): bool
    {
        if (! $invitation->isPending()) {
            return false;
        }

        return $user->id === $invitation->user_id;
    }

    /**
     * The inviter or a subject admin can retract a pending invitation.
     *
     * "Subject admin" is determined by checking the 'manageMembers'
     * ability on the invitable, which each subject's policy defines.
     */
    public function retract(User $user, Invitation $invitation): bool
    {
        if (! $invitation->isPending()) {
            return false;
        }

        // The person who sent the invitation can always retract it
        if ($user->id === $invitation->inviter_id) {
            return true;
        }

        // Subject admins (e.g. band owners/admins) can retract
        $invitable = $invitation->invitable;
        if ($invitable && $user->can('manageMembers', $invitable)) {
            return true;
        }

        return false;
    }
}
