<?php

namespace CorvMC\Membership\Listeners;

use CorvMC\Membership\Notifications\BandInvitationAcceptedNotification;
use CorvMC\Support\Events\InvitationAccepted;

class SendBandInvitationAcceptedNotification
{
    public function handle(InvitationAccepted $event): void
    {
        $invitation = $event->invitation;

        if ($invitation->invitable_type !== 'band') {
            return;
        }

        $band = $invitation->invitable;

        // Notify band owner and admins (excluding the new member themselves)
        $notifiedIds = [];

        if ($band->owner && $band->owner->id !== $invitation->user_id) {
            $band->owner->notify(new BandInvitationAcceptedNotification($invitation));
            $notifiedIds[] = $band->owner->id;
        }

        $admins = $band->members()
            ->wherePivot('role', 'admin')
            ->where('users.id', '!=', $invitation->user_id)
            ->whereNotIn('users.id', $notifiedIds)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new BandInvitationAcceptedNotification($invitation));
        }
    }
}
