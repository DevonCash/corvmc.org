<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendMembershipReminders as SendMembershipRemindersAction;
use Illuminate\Console\Command;

class SendMembershipReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memberships:send-reminders {--dry-run : Show what would be sent without actually sending} {--inactive-days=90 : Days of inactivity before sending reminder}';

    /**
     * The console command description.
     */
    protected $description = 'Send membership reminders to inactive users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $inactiveDays = (int) $this->option('inactive-days');

        $this->info('💌 Sending membership reminders...');
        $this->line('==================================');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
        }

        $this->info("Checking for users inactive for {$inactiveDays}+ days...");

        $results = SendMembershipRemindersAction::run($isDryRun, $inactiveDays);

        if ($results['total'] === 0) {
            $this->info('No inactive users found that need membership reminders.');

            return 0;
        }

        $this->info("Found {$results['total']} inactive users:");

        foreach ($results['users'] as $user) {
            $lastReservation = $user['last_reservation'] ?
                $user['last_reservation']->format('M j, Y') : 'Never';

            $this->line("- {$user['name']} ({$user['email']})");
            $this->line("  Last reservation: {$lastReservation}");

            match ($user['status']) {
                'sent' => $this->info('  ✓ Membership reminder sent'),
                'failed' => $this->error("  ✗ Failed to send reminder: {$user['error']}"),
                'dry_run' => $this->line('  → Would send membership reminder'),
                default => $this->line('  ? Unknown status')
            };
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("   Total inactive users: {$results['total']}");
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
            $this->info('Membership reminders have been sent!');
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}
