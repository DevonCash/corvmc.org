<?php

namespace App\Listeners\Volunteering;

use CorvMC\Volunteering\Events\VolunteerReleased;
use CorvMC\Volunteering\Notifications\ShiftReleasedNotification;

class SendShiftReleasedNotification
{
    public function handle(VolunteerReleased $event): void
    {
        $hourLog = $event->hourLog->loadMissing(['shift.position', 'shift.event', 'user']);

        $hourLog->user->notify(new ShiftReleasedNotification($hourLog));
    }
}
