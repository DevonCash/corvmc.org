<?php

namespace CorvMC\SpaceManagement\Console;

use CorvMC\SpaceManagement\Notifications\RehearsalReminderNotification;
use CorvMC\Support\Models\Invitation;
use Illuminate\Console\Command;

class SendRehearsalRemindersCommand extends Command
{
    protected $signature = 'rehearsals:send-reminders';

    protected $description = 'Send reminders to pending rehearsal invitees 24 hours before the reservation';

    public function handle(): int
    {
        $pendingInvitations = Invitation::query()
            ->where('invitable_type', 'rehearsal_reservation')
            ->where('status', 'pending')
            ->whereNull('data->reminded_at')
            ->whereHas('invitable', function ($query) {
                $query->whereBetween('reserved_at', [
                    now()->addHours(23),
                    now()->addHours(25),
                ]);
            })
            ->with(['invitable', 'user'])
            ->get();

        $sent = 0;

        foreach ($pendingInvitations as $invitation) {

            $invitation->user->notify(new RehearsalReminderNotification($invitation));

            // Mark as reminded
            $invitation->update([
                'data' => array_merge($invitation->data ?? [], [
                    'reminded_at' => now()->toIso8601String(),
                ]),
            ]);

            $sent++;
        }

        $this->info("Sent {$sent} rehearsal reminder(s).");

        return self::SUCCESS;
    }
}
