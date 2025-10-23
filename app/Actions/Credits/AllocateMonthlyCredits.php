<?php

namespace App\Actions\Credits;

use App\Enums\CreditType;
use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AllocateMonthlyCredits
{
    use AsAction;

    /**
     * Allocate monthly credits based on billing cycle.
     *
     * Uses transaction log to determine when next allocation is due.
     * Allocates 1 month after last allocation (using addMonthNoOverflow to handle month-end edge cases).
     *
     * Handles both practice space (reset) and equipment (rollover) credits.
     */
    public function handle(
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
                        'description' => "Monthly equipment credits allocation" .
                                       ($actualAmount < $amount ? " (capped at {$maxBalance})" : ""),
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
     * Handle mid-month upgrade by adding credit delta.
     *
     * Called when user upgrades their subscription within the same billing cycle.
     * Adds the difference between new tier amount and previous tier amount.
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
                        'description' => "Mid-month upgrade: tier changed from {$previousAmount} to {$newAmount}" .
                                       ($actualDelta < $tierDelta ? " (capped at {$maxBalance})" : ""),
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
     */
    protected function getDefaultCreditConfig(CreditType $creditType): array
    {
        return match($creditType) {
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
