<?php

namespace App\Console\Commands;

use App\Actions\GoogleCalendar\BulkSyncReservationsToGoogleCalendar;
use Illuminate\Console\Command;

class BulkSyncGoogleCalendar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-calendar:bulk-sync {--resync-all : Re-sync all reservations, even those already synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk sync all future/current reservations to Google Calendar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting bulk sync of reservations to Google Calendar...');

        $resyncAll = $this->option('resync-all');

        if ($resyncAll) {
            $this->warn('Re-syncing ALL reservations (including already synced ones)');
        }

        $result = BulkSyncReservationsToGoogleCalendar::run($resyncAll);

        if (! $result['success']) {
            $this->error($result['message']);

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Bulk sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $result['total']],
                ['Successfully synced', $result['synced']],
                ['Failed', $result['failed']],
                ['Skipped', $result['skipped']],
            ]
        );

        if ($result['failed'] > 0) {
            $this->warn('Some reservations failed to sync. Check the logs for details.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
