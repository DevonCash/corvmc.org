<?php

namespace App\Services;

use App\Models\User;
use App\Models\CreditTransaction;
use Carbon\Carbon;

/**
 * Backward compatibility wrapper for CreditService.
 *
 * All business logic has been migrated to individual Action classes.
 * This service now delegates to those actions to maintain compatibility
 * with existing code during the migration period.
 *
 * New code should use Actions directly:
 * - \App\Actions\Credits\GetBalance::run($user, $creditType)
 * - \App\Actions\Credits\AddCredits::run($user, $amount, ...)
 * - \App\Actions\Credits\DeductCredits::run($user, $amount, ...)
 * - \App\Actions\Credits\AllocateMonthlyCredits::run($user, $amount, $creditType)
 * - \App\Actions\Credits\RedeemPromoCode::run($user, $code)
 */
class CreditService
{
    /**
     * Get user's current credit balance.
     */
    public function getBalance(User $user, string $creditType = 'free_hours'): int
    {
        return \App\Actions\Credits\GetBalance::run($user, $creditType);
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
        return \App\Actions\Credits\AddCredits::run(
            $user,
            $amount,
            $source,
            $sourceId,
            $description,
            $creditType,
            $expiresAt
        );
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
        return \App\Actions\Credits\DeductCredits::run(
            $user,
            $amount,
            $source,
            $sourceId,
            $creditType
        );
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
        \App\Actions\Credits\AllocateMonthlyCredits::run($user, $amount, $creditType);
    }

    /**
     * Redeem promo code.
     */
    public function redeemPromoCode(User $user, string $code): CreditTransaction
    {
        return \App\Actions\Credits\RedeemPromoCode::run($user, $code);
    }

    /**
     * Process all pending allocations.
     *
     * Note: This method is deprecated. Use the console command instead:
     * php artisan credits:process-allocations
     */
    public function processPendingAllocations(): void
    {
        \App\Actions\Credits\ProcessPendingAllocations::run();
    }
}
