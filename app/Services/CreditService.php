<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use App\Models\CreditAllocation;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Exceptions\PromoCodeMaxUsesException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CreditService
{
    const PRACTICE_SPACE_BLOCKS_PER_DOLLAR = 2; // 2 blocks (1 hour) per $5
    const PRACTICE_SPACE_DOLLAR_AMOUNT = 5;
    const EQUIPMENT_CREDITS_PER_DOLLAR = 1;
    const MINUTES_PER_BLOCK = 30;

    /**
     * Get user's current credit balance.
     */
    public function getBalance(User $user, string $creditType = 'free_hours'): int
    {
        return UserCredit::where('user_id', $user->id)
            ->where('credit_type', $creditType)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->value('balance') ?? 0;
    }

    /**
     * Convert blocks to hours for display.
     */
    public function blocksToHours(int $blocks): float
    {
        return ($blocks * self::MINUTES_PER_BLOCK) / 60;
    }

    /**
     * Convert hours to blocks for storage.
     */
    public function hoursToBlocks(float $hours): int
    {
        return (int) ceil(($hours * 60) / self::MINUTES_PER_BLOCK);
    }

    /**
     * Add credits to user's account (transaction-safe).
     */
    public function addCredits(
        User $user,
        int $amount,
        string $source,
        ?int $sourceId = null,
        ?string $description = null,
        string $creditType = 'free_hours',
        ?Carbon $expiresAt = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $source, $sourceId, $description, $creditType, $expiresAt) {
            // Lock user credit record for update
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType],
                    array_merge(
                        ['balance' => 0],
                        $expiresAt ? ['expires_at' => $expiresAt] : [],
                        $this->getDefaultCreditConfig($creditType)
                    )
                );

            // Update balance
            $credit->balance += $amount;
            $credit->save();

            // Record transaction
            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType,
                'amount' => $amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Deduct credits (e.g., when creating reservation).
     */
    public function deductCredits(
        User $user,
        int $amount,
        string $source,
        ?int $sourceId = null,
        string $creditType = 'free_hours'
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $source, $sourceId, $creditType) {
            $credit = UserCredit::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('credit_type', $creditType)
                ->first();

            if (!$credit || $credit->balance < $amount) {
                $currentBalance = $credit->balance ?? 0;
                throw new InsufficientCreditsException(
                    "User has {$currentBalance} credits but needs {$amount}"
                );
            }

            $credit->balance -= $amount;
            $credit->save();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType,
                'amount' => -$amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Allocate monthly credits based on subscription.
     * Handles both practice space (reset) and equipment (rollover) credits.
     */
    public function allocateMonthlyCredits(
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

    /**
     * Process all pending allocations.
     * Run via scheduled command: php artisan credits:allocate
     */
    public function processPendingAllocations(): void
    {
        $allocations = CreditAllocation::where('is_active', true)
            ->where('next_allocation_at', '<=', now())
            ->get();

        foreach ($allocations as $allocation) {
            $this->processAllocation($allocation);
        }
    }

    protected function processAllocation(CreditAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $this->allocateMonthlyCredits(
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

    /**
     * Redeem promo code.
     */
    public function redeemPromoCode(User $user, string $code): CreditTransaction
    {
        $promo = PromoCode::where('code', $code)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return DB::transaction(function () use ($user, $promo) {
            // Check if already redeemed
            if ($promo->redemptions()->where('user_id', $user->id)->exists()) {
                throw new PromoCodeAlreadyRedeemedException();
            }

            // Check max uses
            if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
                throw new PromoCodeMaxUsesException();
            }

            // Add credits
            $transaction = $this->addCredits(
                $user,
                $promo->credit_amount,
                'promo_code',
                $promo->id,
                "Promo code: {$promo->code}",
                $promo->credit_type
            );

            // Record redemption
            PromoCodeRedemption::create([
                'promo_code_id' => $promo->id,
                'user_id' => $user->id,
                'credit_transaction_id' => $transaction->id,
                'redeemed_at' => now(),
            ]);

            // Increment uses
            $promo->increment('uses_count');

            return $transaction;
        });
    }
}
