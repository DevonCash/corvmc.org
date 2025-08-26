<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\ReservationConfirmationReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReservationConfirmationReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reservations:send-confirmation-reminders {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send confirmation reminder notifications for pending reservations (3 days before)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        // Find reservations that are:
        // - Pending status
        // - Starting in 3 days (72 hours from now, with some buffer)
        $threeDaysFromNow = Carbon::now()->addDays(3);
        $startOfTargetDay = $threeDaysFromNow->copy()->startOfDay();
        $endOfTargetDay = $threeDaysFromNow->copy()->endOfDay();
        
        $reservations = Reservation::with('user')
            ->where('status', 'pending')
            ->whereBetween('reserved_at', [$startOfTargetDay, $endOfTargetDay])
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('No pending reservations found for 3 days from now that need confirmation reminders.');
            return 0;
        }

        $this->info("Found {$reservations->count()} pending reservations for 3 days from now:");

        foreach ($reservations as $reservation) {
            $this->line("- {$reservation->user->name}: {$reservation->time_range} (Status: {$reservation->status})");
            
            if (!$isDryRun) {
                try {
                    $reservation->user->notify(new ReservationConfirmationReminderNotification($reservation));
                    $this->info("  ✓ Confirmation reminder sent to {$reservation->user->email}");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to send confirmation reminder to {$reservation->user->email}: {$e->getMessage()}");
                }
            } else {
                $this->line("  → Would send confirmation reminder to {$reservation->user->email}");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No notifications were actually sent.');
            $this->info('Run without --dry-run to actually send the confirmation reminders.');
        } else {
            $this->info('Reservation confirmation reminders have been sent!');
        }

        return 0;
    }
}