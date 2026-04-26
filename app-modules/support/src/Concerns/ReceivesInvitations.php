<?php

namespace CorvMC\Support\Concerns;

use CorvMC\Support\Models\Invitation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adds invitation relationships from the invitee's perspective.
 *
 * Apply to User (or any model that can receive invitations).
 */
trait ReceivesInvitations
{
    public function receivedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'user_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'inviter_id');
    }
}
