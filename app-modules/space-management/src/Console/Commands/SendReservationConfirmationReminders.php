<?php

namespace CorvMC\SpaceManagement\Console\Commands;

use App\Actions\Notifications\SendReservationConfirmationReminders as SendReservationConfirmationRemindersAction;
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
    protected $description = 'Send confirmation reminder notifications for pending reservations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('📋 Sending reservation confirmation reminders...');
        $this->line('============================================');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
        }

        $results = SendReservationConfirmationRemindersAction::run($isDryRun);

        if ($results['total'] === 0) {
            $this->info('No pending reservations found that need confirmation reminders.');

            return 0;
        }

        $this->info("Found {$results['total']} pending reservations:");

        foreach ($results['reservations'] as $reservation) {
            $this->line("- {$reservation['user_name']}: {$reservation['time_range']}");
            $this->line("  Created: {$reservation['created_at']->format('M j, Y g:i A')}");

            match ($reservation['status']) {
                'sent' => $this->info("  ✓ Confirmation reminder sent to {$reservation['user_email']}"),
                'failed' => $this->error("  ✗ Failed to send reminder to {$reservation['user_email']}: {$reservation['error']}"),
                'dry_run' => $this->line("  → Would send confirmation reminder to {$reservation['user_email']}"),
                default => $this->line("  ? Unknown status for {$reservation['user_email']}")
            };
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("   Total reservations: {$results['total']}");
        $this->line("   Successfully sent: {$results['sent']}");
        $this->line("   Failed: {$results['failed']}");

        if (! empty($results['errors'])) {
            $this->line('   Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("     • {$error}");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No notifications were actually sent.');
            $this->info('Run without --dry-run to actually send the reminders.');
        } else {
            $this->info('Reservation confirmation reminders have been sent!');
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}
