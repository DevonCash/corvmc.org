<?php

namespace App\Listeners\Volunteering;

use CorvMC\Volunteering\Events\HoursApproved;
use CorvMC\Volunteering\Events\HoursRejected;
use CorvMC\Volunteering\Notifications\HoursReviewedNotification;
use Illuminate\Events\Dispatcher;

class SendHoursReviewedNotification
{
    public function handleHoursApproved(HoursApproved $event): void
    {
        $hourLog = $event->hourLog->loadMissing(['user', 'position']);

        $hourLog->user->notify(new HoursReviewedNotification($hourLog, approved: true));
    }

    public function handleHoursRejected(HoursRejected $event): void
    {
        $hourLog = $event->hourLog->loadMissing(['user', 'position']);

        $hourLog->user->notify(new HoursReviewedNotification($hourLog, approved: false));
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(HoursApproved::class, [self::class, 'handleHoursApproved']);
        $events->listen(HoursRejected::class, [self::class, 'handleHoursRejected']);
    }
}
