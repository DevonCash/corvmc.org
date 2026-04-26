<?php

namespace CorvMC\SpaceManagement\Listeners;

use CorvMC\SpaceManagement\Notifications\RehearsalAttendanceRequestedNotification;
use CorvMC\Support\Events\InvitationCreated;

class SendRehearsalAttendanceNotification
{
    public function handle(InvitationCreated $event): void
    {
        $invitation = $event->invitation;

        if ($invitation->invitable_type !== 'rehearsal_reservation') {
            return;
        }

        $invitation->user->notify(new RehearsalAttendanceRequestedNotification($invitation));
    }
}
