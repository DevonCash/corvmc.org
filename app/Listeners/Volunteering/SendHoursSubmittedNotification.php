<?php

namespace App\Listeners\Volunteering;

use App\Models\User;
use CorvMC\Volunteering\Events\HoursSubmitted;
use CorvMC\Volunteering\Notifications\HoursSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class SendHoursSubmittedNotification
{
    public function handle(HoursSubmitted $event): void
    {
        $hourLog = $event->hourLog->loadMissing(['user', 'position']);

        $approvers = User::permission('volunteer.hours.approve')->get();

        Notification::send($approvers, new HoursSubmittedNotification($hourLog));
    }
}
