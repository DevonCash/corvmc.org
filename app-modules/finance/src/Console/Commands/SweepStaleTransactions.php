<?php

namespace CorvMC\Finance\Console\Commands;

use CorvMC\Finance\Facades\Finance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SweepStaleTransactions extends Command
{
    protected $signature = 'finance:sweep-stale
        {--hours=25 : Hours after which a pending Stripe transaction is considered stale}
        {--dry-run : Report what would happen without making changes}';

    protected $description = 'Find stale pending Stripe transactions and resolve them via the Stripe API';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in dry-run mode — no changes will be made.');
            $this->warn('(dry-run queries Stripe but does not transition Transactions)');
            $this->newLine();
        }

        $this->info("Sweeping Stripe transactions older than {$hours}h...");

        // In dry-run mode we still want to report what would happen,
        // but the manager always applies changes. So dry-run stays in the command
        // as a gate: we just skip calling the manager and report counts from a query.
        if ($dryRun) {
            return $this->dryRun($hours);
        }

        $result = Finance::sweepStaleTransactions($hours);

        $this->info("Done. Settled: {$result['settled']}, Failed: {$result['failed']}, Errors: " . count($result['errors']));

        foreach ($result['errors'] as $error) {
            $this->error("  Transaction #{$error['transaction_id']}: {$error['error']}");
        }

        if (! empty($result['errors'])) {
            Log::error('finance:sweep-stale: Completed with errors', $result);
        }

        return empty($result['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    private function dryRun(int $hours): int
    {
        $cutoff = now()->subHours($hours);

        $count = \CorvMC\Finance\Models\Transaction::query()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->info("Found {$count} stale transaction(s) that would be processed.");

        return Command::SUCCESS;
    }
}
