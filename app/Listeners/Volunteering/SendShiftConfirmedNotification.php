<?php

namespace App\Listeners\Volunteering;

use CorvMC\Volunteering\Events\VolunteerConfirmed;
use CorvMC\Volunteering\Notifications\ShiftConfirmedNotification;

class SendShiftConfirmedNotification
{
    public function handle(VolunteerConfirmed $event): void
    {
        $hourLog = $event->hourLog->loadMissing(['shift.position', 'shift.event', 'user']);

        $hourLog->user->notify(new ShiftConfirmedNotification($hourLog));
    }
}
