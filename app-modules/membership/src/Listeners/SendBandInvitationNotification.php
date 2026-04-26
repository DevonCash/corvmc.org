<?php

namespace CorvMC\Membership\Listeners;

use CorvMC\Membership\Notifications\BandInvitationNotification;
use CorvMC\Support\Events\InvitationCreated;

class SendBandInvitationNotification
{
    public function handle(InvitationCreated $event): void
    {
        $invitation = $event->invitation;

        if ($invitation->invitable_type !== 'band') {
            return;
        }

        // Self-invites (e.g. RSVP) don't get a notification — the user just did the action
        if ($invitation->inviter_id === null) {
            return;
        }

        $invitation->user->notify(new BandInvitationNotification($invitation));
    }
}
