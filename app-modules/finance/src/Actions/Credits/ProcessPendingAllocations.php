<?php

namespace CorvMC\Finance\Actions\Credits;

use CorvMC\Finance\Services\CreditService;

/**
 * @deprecated Use CreditService::processPendingAllocations() instead
 * This action is maintained for backward compatibility only.
 * New code should use the CreditService directly.
 */
class ProcessPendingAllocations
{
    public string $commandSignature = 'credits:allocate {--dry-run : Preview allocations without executing}';

    public string $commandDescription = 'Process all pending credit allocations';

    /**
     * @deprecated Use CreditService::processPendingAllocations() instead
     */
    public function handle(): void
    {
        $dryRun = method_exists($this, 'option') ? ($this->option('dry-run') ?? false) : false;

        $summary = app(CreditService::class)->processPendingAllocations($dryRun);

        // Output results if running as command
        if (method_exists($this, 'info')) {
            if ($summary['total'] === 0) {
                $this->info('No pending allocations to process.');
                return;
            }

            $this->info("Processed {$summary['processed']} of {$summary['total']} allocation(s)");

            foreach ($summary['details'] as $detail) {
                $symbol = match($detail['status']) {
                    'processed' => '✓',
                    'dry_run' => '→',
                    'error' => '✗',
                    default => '-'
                };

                $message = "  {$symbol} User {$detail['user_id']}: {$detail['amount']} {$detail['credit_type']} credits";
                if (isset($detail['error'])) {
                    $message .= " (Error: {$detail['error']})";
                }

                $this->line($message);
            }

            if ($dryRun) {
                $this->warn('DRY RUN - No changes were made');
            } elseif ($summary['errors'] > 0) {
                $this->warn("Completed with {$summary['errors']} error(s)");
            } else {
                $this->info('✓ All allocations processed successfully');
            }
        }
    }

    // All helper methods removed - functionality moved to CreditService
}
