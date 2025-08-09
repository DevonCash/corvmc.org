<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\ReservationReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReservationReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reservations:send-reminders {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for upcoming reservations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        // Find reservations that are:
        // - Confirmed
        // - Starting tomorrow (24 hours from now, with some buffer)
        $tomorrow = Carbon::now()->addDay();
        $startOfTomorrow = $tomorrow->copy()->startOfDay();
        $endOfTomorrow = $tomorrow->copy()->endOfDay();
        
        $reservations = Reservation::with('user')
            ->where('status', 'confirmed')
            ->whereBetween('reserved_at', [$startOfTomorrow, $endOfTomorrow])
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('No reservations found for tomorrow that need reminders.');
            return 0;
        }

        $this->info("Found {$reservations->count()} reservations for tomorrow:");

        foreach ($reservations as $reservation) {
            $this->line("- {$reservation->user->name}: {$reservation->time_range}");
            
            if (!$isDryRun) {
                try {
                    $reservation->user->notify(new ReservationReminderNotification($reservation));
                    $this->info("  ✓ Reminder sent to {$reservation->user->email}");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to send reminder to {$reservation->user->email}: {$e->getMessage()}");
                }
            } else {
                $this->line("  → Would send reminder to {$reservation->user->email}");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No notifications were actually sent.');
            $this->info('Run without --dry-run to actually send the reminders.');
        } else {
            $this->info('Reservation reminders have been sent!');
        }

        return 0;
    }
}