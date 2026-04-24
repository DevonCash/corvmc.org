<?php

namespace CorvMC\Finance\Console\Commands;

use CorvMC\Finance\Facades\Finance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileTransactions extends Command
{
    protected $signature = 'finance:reconcile
        {--days=7 : How many days back to reconcile cleared Stripe transactions}
        {--archive-days=90 : Archive and delete webhook events older than this many days}
        {--skip-archive : Skip the webhook event archive/cleanup step}
        {--dry-run : Report what would happen without making changes}';

    protected $description = 'Reconcile cleared Stripe transactions against Stripe API and archive old webhook events';

    public function handle(): int
    {
        $exitCode = Command::SUCCESS;

        // Part 1: Reconcile cleared Stripe transactions
        $exitCode = $this->runReconcile($exitCode);

        // Part 2: Archive and clean up old webhook events
        if (! $this->option('skip-archive')) {
            $exitCode = $this->runArchive($exitCode);
        }

        return $exitCode;
    }

    private function runReconcile(int $currentExitCode): int
    {
        $days = (int) $this->option('days');

        $this->info("Reconciling Stripe transactions cleared in the last {$days} day(s)...");

        $result = Finance::reconcileTransactions($days);

        $this->info("Matched: {$result['matched']}, Mismatches: " . count($result['mismatches']) . ", Errors: {$result['errors']}");

        if (! empty($result['mismatches'])) {
            $this->warn('MISMATCHES FOUND — staff review required:');

            foreach ($result['mismatches'] as $m) {
                $this->warn("  Transaction #{$m['transaction_id']} (Order #{$m['order_id']}): {$m['issue']}");
            }

            Log::warning('finance:reconcile: Mismatches found', $result);

            return Command::FAILURE;
        }

        return $result['errors'] > 0 ? Command::FAILURE : $currentExitCode;
    }

    private function runArchive(int $currentExitCode): int
    {
        $archiveDays = (int) $this->option('archive-days');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $count = \Illuminate\Support\Facades\DB::table('stripe_webhook_events')
                ->where('created_at', '<', now()->subDays($archiveDays))
                ->count();

            $this->warn("(dry-run) Would archive and delete {$count} webhook event(s) older than {$archiveDays} days.");

            return $currentExitCode;
        }

        $result = Finance::archiveWebhookEvents($archiveDays);

        if ($result['archived'] === 0) {
            $this->info('No webhook events to archive.');
        } else {
            $this->info("Archived {$result['archived']} event(s) to storage/{$result['file']}.");

            Log::info('finance:reconcile: Archived webhook events', $result);
        }

        return $currentExitCode;
    }
}
