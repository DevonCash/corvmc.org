<?php

namespace App\Console\Commands;

use App\Services\NotificationSchedulingService;
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

    public function __construct(
        private NotificationSchedulingService $notificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('🔔 Sending reservation reminders...');
        $this->line('==================================');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
        }

        $results = $this->notificationService->sendReservationReminders($isDryRun);

        if ($results['total'] === 0) {
            $this->info('No reservations found for tomorrow that need reminders.');
            return 0;
        }

        $this->info("Found {$results['total']} reservations for tomorrow:");

        foreach ($results['reservations'] as $reservation) {
            $this->line("- {$reservation['user_name']}: {$reservation['time_range']}");
            
            match ($reservation['status']) {
                'sent' => $this->info("  ✓ Reminder sent to {$reservation['user_email']}"),
                'failed' => $this->error("  ✗ Failed to send reminder to {$reservation['user_email']}: {$reservation['error']}"),
                'dry_run' => $this->line("  → Would send reminder to {$reservation['user_email']}"),
                default => $this->line("  ? Unknown status for {$reservation['user_email']}")
            };
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("   Total reservations: {$results['total']}");
        $this->line("   Successfully sent: {$results['sent']}");
        $this->line("   Failed: {$results['failed']}");

        if (!empty($results['errors'])) {
            $this->line("   Errors:");
            foreach ($results['errors'] as $error) {
                $this->line("     • {$error}");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No notifications were actually sent.');
            $this->info('Run without --dry-run to actually send the reminders.');
        } else {
            $this->info('Reservation reminders have been sent!');
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}