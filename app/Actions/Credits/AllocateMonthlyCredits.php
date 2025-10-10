<?php

namespace App\Actions\Credits;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class AllocateMonthlyCredits
{
    use AsAction;

    /**
     * Allocate monthly credits based on subscription.
     * Handles both practice space (reset) and equipment (rollover) credits.
     */
    public function handle(
        User $user,
        int $amount,
        string $creditType = 'free_hours'
    ): void {
        // Check if allocation already exists for this month
        $allocationKey = "credit_allocation.{$user->id}.{$creditType}." . now()->format('Y-m');

        if (Cache::get($allocationKey)) {
            return; // Already allocated this month
        }

        DB::transaction(function () use ($user, $amount, $creditType, $allocationKey) {
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType],
                    $this->getDefaultCreditConfig($creditType)
                );

            // Handle different allocation strategies
            if ($creditType === 'free_hours') {
                // Practice Space: RESET to subscription amount (no rollover)
                $oldBalance = $credit->balance;
                $credit->balance = $amount;
                $credit->save();

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'credit_type' => $creditType,
                    'amount' => $amount,
                    'balance_after' => $credit->balance,
                    'source' => 'monthly_reset',
                    'description' => "Monthly practice space credits reset for " . now()->format('F Y') . " (previous: {$oldBalance} blocks)",
                    'created_at' => now(),
                ]);
            } elseif ($creditType === 'equipment_credits') {
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
                        'credit_type' => $creditType,
                        'amount' => $actualAmount,
                        'balance_after' => $credit->balance,
                        'source' => 'monthly_allocation',
                        'description' => "Monthly equipment credits allocation for " . now()->format('F Y') .
                                       ($actualAmount < $amount ? " (capped at {$maxBalance})" : ""),
                        'metadata' => json_encode([
                            'requested_amount' => $amount,
                            'actual_amount' => $actualAmount,
                            'cap_reached' => $credit->balance >= $maxBalance,
                        ]),
                        'created_at' => now(),
                    ]);
                }
            }

            // Mark as allocated for this month
            Cache::put($allocationKey, true, now()->endOfMonth());
        });
    }

    /**
     * Get default configuration for a credit type.
     */
    protected function getDefaultCreditConfig(string $creditType): array
    {
        return match($creditType) {
            'free_hours' => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
            'equipment_credits' => [
                'balance' => 0,
                'max_balance' => 250,
                'rollover_enabled' => true,
            ],
            default => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
        };
    }
}
