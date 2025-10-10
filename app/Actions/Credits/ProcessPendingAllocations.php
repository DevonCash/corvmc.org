<?php

namespace App\Actions\Credits;

use App\Models\CreditAllocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPendingAllocations
{
    use AsAction;

    public string $commandSignature = 'credits:allocate {--dry-run : Preview allocations without executing}';
    public string $commandDescription = 'Process all pending credit allocations';

    /**
     * Process all pending allocations.
     * Can be run via: php artisan credits:allocate
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run') ?? false;

        $allocations = CreditAllocation::where('is_active', true)
            ->where('next_allocation_at', '<=', now())
            ->get();

        if ($allocations->isEmpty()) {
            $this->info('No pending allocations to process.');
            return;
        }

        $this->info("Processing {$allocations->count()} allocation(s)...");

        foreach ($allocations as $allocation) {
            if ($dryRun) {
                $this->line("  → Would allocate {$allocation->amount} {$allocation->credit_type} credits to user {$allocation->user_id}");
            } else {
                $this->processAllocation($allocation);
                $this->line("  ✓ Allocated {$allocation->amount} {$allocation->credit_type} credits to user {$allocation->user_id}");
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No changes were made');
        } else {
            $this->info('✓ All allocations processed successfully');
        }
    }

    protected function processAllocation(CreditAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            AllocateMonthlyCredits::run(
                $allocation->user,
                $allocation->amount,
                $allocation->credit_type
            );

            // Update next allocation date
            $allocation->last_allocated_at = now();
            $allocation->next_allocation_at = $this->calculateNextAllocation(
                $allocation->frequency,
                now()
            );
            $allocation->save();
        });
    }

    protected function calculateNextAllocation(string $frequency, Carbon $from): Carbon
    {
        return match($frequency) {
            'monthly' => $from->copy()->addMonth()->startOfMonth(),
            'weekly' => $from->copy()->addWeek(),
            'one_time' => $from->copy()->addYears(100), // Effectively never
            default => $from->copy()->addMonth(),
        };
    }
}
