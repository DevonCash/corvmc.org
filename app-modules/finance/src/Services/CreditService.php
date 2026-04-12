<?php

namespace CorvMC\Finance\Services;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\CreditAllocation;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\Finance\Models\UserCredit;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing user credits and allocations.
 * 
 * This service handles credit allocation, adjustments, and transaction
 * tracking for different credit types (free hours, equipment credits, etc).
 */
class CreditService
{
    /**
     * Allocate monthly credits based on billing cycle.
     *
     * Uses transaction log to determine when next allocation is due.
     * Allocates 1 month after last allocation (using addMonthNoOverflow to handle month-end edge cases).
     *
     * Handles both practice space (reset) and equipment (rollover) credits.
     * 
     * @param User $user The user to allocate credits to
     * @param int $amount The amount of credits to allocate
     * @param CreditType $creditType The type of credits to allocate
     */
    public function allocateMonthlyCredits(
        User $user,
        int $amount,
        CreditType $creditType = CreditType::FreeHours
    ): void {
        // Find last allocation transaction
        $lastAllocation = CreditTransaction::where('user_id', $user->id)
            ->where('credit_type', $creditType->value)
            ->where('source', $creditType === CreditType::FreeHours ? 'monthly_reset' : 'monthly_allocation')
            ->latest('created_at')
            ->first();

        // Determine if allocation is due
        if ($lastAllocation) {
            $nextAllocationDate = $lastAllocation->created_at->copy()->addMonthNoOverflow();

            // If not time for regular allocation, check if this is a mid-month upgrade
            if (now()->lt($nextAllocationDate)) {
                // Check if this is a tier upgrade by comparing to previous allocated amount
                $previousAmount = $this->getLastAllocatedAmount($user, $creditType);
                $currentBalance = $user->getCreditBalance($creditType);

                if ($amount > $previousAmount) {
                    // Mid-month upgrade - add the tier delta
                    $this->handleMidMonthUpgrade($user, $amount, $creditType, $currentBalance);
                }

                // Otherwise, no action needed (downgrade keeps peak amount until next cycle)
                return;
            }
        }
        // If no last allocation, this is the first time - proceed

        DB::transaction(function () use ($user, $amount, $creditType) {
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType->value],
                    $this->getDefaultCreditConfig($creditType)
                );

            // Handle different allocation strategies
            if ($creditType === CreditType::FreeHours) {
                // Practice Space: RESET to subscription amount (no rollover)
                $oldBalance = $credit->balance;
                $delta = $amount - $oldBalance; // Actual change
                $credit->balance = $amount;
                $credit->save();

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'credit_type' => $creditType->value,
                    'amount' => $delta, // Record actual delta, not allocation amount
                    'balance_after' => $credit->balance,
                    'source' => 'monthly_reset',
                    'description' => "Monthly reset to {$amount} blocks (was {$oldBalance})",
                    'metadata' => json_encode([
                        'allocated_amount' => $amount,
                    ]),
                    'created_at' => now(),
                ]);
            } elseif ($creditType === CreditType::EquipmentCredits) {
                // Equipment: ADD to existing balance with cap (rollover enabled)
                $oldBalance = $credit->balance;
                $maxBalance = $credit->max_balance ?? 250; // Default cap

                // Calculate how much we can add without exceeding cap
                $availableSpace = max(0, $maxBalance - $oldBalance);
                $actualAmount = min($amount, $availableSpace);

                if ($actualAmount > 0) {
                    $credit->balance += $actualAmount;
                    $credit->save();

                    CreditTransaction::create([
                        'user_id' => $user->id,
                        'credit_type' => $creditType->value,
                        'amount' => $actualAmount,
                        'balance_after' => $credit->balance,
                        'source' => 'monthly_allocation',
                        'description' => 'Monthly equipment credits allocation'.
                                       ($actualAmount < $amount ? " (capped at {$maxBalance})" : ''),
                        'metadata' => json_encode([
                            'allocated_amount' => $amount,
                            'requested_amount' => $amount,
                            'actual_amount' => $actualAmount,
                            'cap_reached' => $credit->balance >= $maxBalance,
                        ]),
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Process all pending credit allocations.
     * Can be run via scheduled job or manually.
     * 
     * @param bool $dryRun If true, preview allocations without executing
     * @return array Summary of processed allocations
     */
    public function processPendingAllocations(bool $dryRun = false): array
    {
        $allocations = CreditAllocation::where('is_active', true)
            ->where('next_allocation_at', '<=', now())
            ->get();

        $summary = [
            'total' => $allocations->count(),
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($allocations as $allocation) {
            try {
                if ($dryRun) {
                    $summary['details'][] = [
                        'user_id' => $allocation->user_id,
                        'amount' => $allocation->amount,
                        'credit_type' => $allocation->credit_type,
                        'status' => 'dry_run',
                    ];
                    $summary['skipped']++;
                } else {
                    $this->processAllocation($allocation);
                    $summary['details'][] = [
                        'user_id' => $allocation->user_id,
                        'amount' => $allocation->amount,
                        'credit_type' => $allocation->credit_type,
                        'status' => 'processed',
                    ];
                    $summary['processed']++;
                }
            } catch (\Exception $e) {
                $summary['details'][] = [
                    'user_id' => $allocation->user_id,
                    'amount' => $allocation->amount,
                    'credit_type' => $allocation->credit_type,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * Adjust a user's credit balance.
     * 
     * @param User $user The user to adjust credits for
     * @param int $amount The amount to adjust (positive or negative)
     * @param CreditType $creditType The type of credits to adjust
     * @param string $source The source of the adjustment (e.g., 'admin_adjustment')
     * @param string|null $description Optional description for the adjustment
     */
    public function adjustCredits(
        User $user, 
        int $amount, 
        CreditType $creditType = CreditType::FreeHours,
        string $source = 'admin_adjustment',
        ?string $description = null
    ): void {
        // Use the User model's addCredit method for consistency
        $user->addCredit($amount, $creditType, $source, $description);
    }

    /**
     * Process a single credit allocation.
     * 
     * @param CreditAllocation $allocation The allocation to process
     */
    protected function processAllocation(CreditAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $this->allocateMonthlyCredits(
                $allocation->user,
                $allocation->amount,
                CreditType::from($allocation->credit_type)
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

    /**
     * Calculate the next allocation date based on frequency.
     * 
     * @param string $frequency The allocation frequency
     * @param Carbon $from The date to calculate from
     * @return Carbon The next allocation date
     */
    protected function calculateNextAllocation(string $frequency, Carbon $from): Carbon
    {
        return match ($frequency) {
            'monthly' => $from->copy()->addMonth()->startOfMonth(),
            'weekly' => $from->copy()->addWeek(),
            'one_time' => $from->copy()->addYears(100), // Effectively never
            default => $from->copy()->addMonth(),
        };
    }

    /**
     * Handle mid-month upgrade by adding credit delta.
     *
     * Called when user upgrades their subscription within the same billing cycle.
     * Adds the difference between new tier amount and previous tier amount.
     * 
     * @param User $user The user to handle the upgrade for
     * @param int $newAmount The new tier amount
     * @param CreditType $creditType The type of credits
     * @param int $currentBalance The current credit balance
     */
    protected function handleMidMonthUpgrade(User $user, int $newAmount, CreditType $creditType, int $currentBalance): void
    {
        DB::transaction(function () use ($user, $newAmount, $creditType, $currentBalance) {
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType->value],
                    $this->getDefaultCreditConfig($creditType)
                );

            // Get the previous allocated amount from transaction history
            $previousAmount = $this->getLastAllocatedAmount($user, $creditType);

            if ($creditType === CreditType::FreeHours) {
                // Calculate delta based on tier difference, not balance
                $tierDelta = $newAmount - $previousAmount;
                $credit->balance += $tierDelta;
                $credit->save();

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'credit_type' => $creditType->value,
                    'amount' => $tierDelta,
                    'balance_after' => $credit->balance,
                    'source' => 'upgrade_adjustment',
                    'description' => "Mid-month upgrade: tier changed from {$previousAmount} to {$newAmount} (+{$tierDelta} blocks)",
                    'metadata' => json_encode([
                        'allocated_amount' => $newAmount,
                        'previous_amount' => $previousAmount,
                        'tier_delta' => $tierDelta,
                    ]),
                    'created_at' => now(),
                ]);
            } elseif ($creditType === CreditType::EquipmentCredits) {
                // Calculate tier delta, then apply cap
                $maxBalance = $credit->max_balance ?? 250;
                $tierDelta = $newAmount - $previousAmount;
                $availableSpace = max(0, $maxBalance - $currentBalance);
                $actualDelta = min($tierDelta, $availableSpace);

                if ($actualDelta > 0) {
                    $credit->balance += $actualDelta;
                    $credit->save();

                    CreditTransaction::create([
                        'user_id' => $user->id,
                        'credit_type' => $creditType->value,
                        'amount' => $actualDelta,
                        'balance_after' => $credit->balance,
                        'source' => 'upgrade_adjustment',
                        'description' => "Mid-month upgrade: tier changed from {$previousAmount} to {$newAmount}".
                                       ($actualDelta < $tierDelta ? " (capped at {$maxBalance})" : ''),
                        'metadata' => json_encode([
                            'allocated_amount' => $newAmount,
                            'previous_amount' => $previousAmount,
                            'tier_delta' => $tierDelta,
                            'actual_delta' => $actualDelta,
                            'cap_reached' => $credit->balance >= $maxBalance,
                        ]),
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Get the last allocated amount from transaction history.
     *
     * Returns the subscription tier amount from the most recent allocation.
     * 
     * @param User $user The user to check
     * @param CreditType $creditType The type of credits
     * @return int The last allocated amount
     */
    protected function getLastAllocatedAmount(User $user, CreditType $creditType): int
    {
        $lastAllocation = CreditTransaction::where('user_id', $user->id)
            ->where('credit_type', $creditType->value)
            ->whereIn('source', ['monthly_reset', 'monthly_allocation', 'upgrade_adjustment'])
            ->latest('created_at')
            ->first();

        if (!$lastAllocation || !$lastAllocation->metadata) {
            return 0;
        }

        $metadata = is_array($lastAllocation->metadata)
            ? $lastAllocation->metadata
            : json_decode($lastAllocation->metadata, true);

        return $metadata['allocated_amount'] ?? 0;
    }

    /**
     * Get default configuration for a credit type.
     * 
     * @param CreditType $creditType The credit type
     * @return array The default configuration
     */
    protected function getDefaultCreditConfig(CreditType $creditType): array
    {
        return match ($creditType) {
            CreditType::FreeHours => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
            CreditType::EquipmentCredits => [
                'balance' => 0,
                'max_balance' => 250,
                'rollover_enabled' => true,
            ],
        };
    }
}