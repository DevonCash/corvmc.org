<?php

namespace CorvMC\Support\Concerns;

use CorvMC\Support\Models\Invitation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds invitation relationships to a model that implements InvitationSubject.
 */
trait HasInvitations
{
    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }

    public function pendingInvitations(): MorphMany
    {
        return $this->invitations()->where('status', 'pending');
    }

    public function acceptedInvitations(): MorphMany
    {
        return $this->invitations()->where('status', 'accepted');
    }

    public function declinedInvitations(): MorphMany
    {
        return $this->invitations()->where('status', 'declined');
    }
}
