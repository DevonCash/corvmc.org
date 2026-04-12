<?php

namespace CorvMC\Finance\Actions\MemberBenefits;

use App\Models\User;
use CorvMC\Finance\Services\MemberBenefitService;

/**
 * @deprecated Use MemberBenefitService::getUserMonthlyFreeHours() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberBenefitService directly.
 */
class GetUserMonthlyFreeHours
{
    public const FREE_HOURS_PER_MONTH = 4; // Default fallback

    /**
     * @deprecated Use MemberBenefitService::getUserMonthlyFreeHours() instead
     */
    public function handle(User $user, ?int $subscriptionAmountInCents = null): int
    {
        return app(MemberBenefitService::class)->getUserMonthlyFreeHours($user, $subscriptionAmountInCents);
    }
}
