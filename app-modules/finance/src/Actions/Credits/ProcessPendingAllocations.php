<?php

namespace CorvMC\Finance\Actions\Credits;

use CorvMC\Finance\Models\CreditAllocation;
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
        $dryRun = method_exists($this, 'option') ? ($this->option('dry-run') ?? false) : false;

        $allocations = CreditAllocation::where('is_active', true)
            ->where('next_allocation_at', '<=', now())
            ->get();

        if ($allocations->isEmpty()) {
            if (method_exists($this, 'info')) {
                $this->info('No pending allocations to process.');
            }

            return;
        }

        if (method_exists($this, 'info')) {
            $this->info("Processing {$allocations->count()} allocation(s)...");
        }

        foreach ($allocations as $allocation) {
            if ($dryRun) {
                if (method_exists($this, 'line')) {
                    $this->line("  → Would allocate {$allocation->amount} {$allocation->credit_type} credits to user {$allocation->user_id}");
                }
            } else {
                $this->processAllocation($allocation);
                if (method_exists($this, 'line')) {
                    $this->line("  ✓ Allocated {$allocation->amount} {$allocation->credit_type} credits to user {$allocation->user_id}");
                }
            }
        }

        if ($dryRun && method_exists($this, 'warn')) {
            $this->warn('DRY RUN - No changes were made');
        } elseif (method_exists($this, 'info')) {
            $this->info('✓ All allocations processed successfully');
        }
    }

    protected function processAllocation(CreditAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            AllocateMonthlyCredits::run(
                $allocation->user,
                $allocation->amount,
                \App\Enums\CreditType::from($allocation->credit_type)
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
        return match ($frequency) {
            'monthly' => $from->copy()->addMonth()->startOfMonth(),
            'weekly' => $from->copy()->addWeek(),
            'one_time' => $from->copy()->addYears(100), // Effectively never
            default => $from->copy()->addMonth(),
        };
    }
}
