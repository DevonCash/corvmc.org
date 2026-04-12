<?php

namespace CorvMC\Finance\Actions\MemberBenefits;

use App\Models\User;
use CorvMC\Finance\Services\MemberBenefitService;

/**
 * @deprecated Use MemberBenefitService::allocateUserMonthlyCredits() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberBenefitService directly.
 */
class AllocateUserMonthlyCredits
{
    /**
     * @deprecated Use MemberBenefitService::allocateUserMonthlyCredits() instead
     */
    public function handle(User $user, ?int $subscriptionAmountInCents = null): void
    {
        app(MemberBenefitService::class)->allocateUserMonthlyCredits($user, $subscriptionAmountInCents);
    }
}
