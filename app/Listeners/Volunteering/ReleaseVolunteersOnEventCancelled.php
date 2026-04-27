<?php

namespace App\Listeners\Volunteering;

use CorvMC\Events\Events\EventCancelled;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;

class ReleaseVolunteersOnEventCancelled
{
    public function handle(EventCancelled $event): void
    {
        $shifts = Shift::where('event_id', $event->event->id)->pluck('id');

        if ($shifts->isEmpty()) {
            return;
        }

        $hourLogs = HourLog::whereIn('shift_id', $shifts)
            ->active()
            ->get();

        $service = app(HourLogService::class);

        foreach ($hourLogs as $hourLog) {
            $service->release($hourLog);
        }
    }
}
