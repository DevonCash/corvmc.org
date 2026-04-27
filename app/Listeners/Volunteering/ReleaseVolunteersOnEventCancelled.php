<?php

namespace App\Listeners\Volunteering;

use CorvMC\Events\Events\EventCancelled;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;
use Illuminate\Support\Facades\Log;

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
            try {
                $service->release($hourLog);
            } catch (\Throwable $e) {
                Log::error('Failed to release volunteer on event cancellation', [
                    'hour_log_id' => $hourLog->id,
                    'event_id' => $event->event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
