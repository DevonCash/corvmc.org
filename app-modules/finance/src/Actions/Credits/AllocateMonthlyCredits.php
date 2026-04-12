<?php

namespace CorvMC\Finance\Actions\Credits;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Services\CreditService;

/**
 * @deprecated Use CreditService::allocateMonthlyCredits() instead
 * This action is maintained for backward compatibility only.
 * New code should use the CreditService directly.
 */
class AllocateMonthlyCredits
{
    /**
     * @deprecated Use CreditService::allocateMonthlyCredits() instead
     */
    public function handle(
        User $user,
        int $amount,
        CreditType $creditType = CreditType::FreeHours
    ): void {
        app(CreditService::class)->allocateMonthlyCredits($user, $amount, $creditType);
    }

    // All helper methods removed - functionality moved to CreditService
}
