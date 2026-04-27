<?php

namespace CorvMC\Volunteering\Console;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Notifications\ShiftReminderNotification;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendShiftReminders extends Command
{
    protected $signature = 'volunteering:send-shift-reminders';

    protected $description = 'Send reminder notifications to volunteers with shifts starting in ~24 hours';

    public function handle(): int
    {
        $hourLogs = HourLog::query()
            ->where('status', Confirmed::getMorphClass())
            ->whereNotNull('shift_id')
            ->whereHas('shift', function ($query) {
                $query->whereBetween('start_at', [
                    now()->addHours(23),
                    now()->addHours(25),
                ]);
            })
            ->with(['shift.position', 'shift.event', 'user'])
            ->get();

        $sent = 0;

        foreach ($hourLogs as $hourLog) {
            $cacheKey = "shift-reminder:{$hourLog->id}";

            if (Cache::has($cacheKey)) {
                continue;
            }

            $hourLog->user->notify(new ShiftReminderNotification($hourLog));

            // Cache for 48 hours to prevent duplicate sends across runs
            Cache::put($cacheKey, true, now()->addHours(48));

            $sent++;
        }

        $this->info("Sent {$sent} shift reminder(s).");

        return self::SUCCESS;
    }
}
