<?php

namespace CorvMC\Finance\Actions\MemberBenefits;

use CorvMC\Finance\Services\MemberBenefitService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use MemberBenefitService::calculateFreeHours() instead
 * This action is maintained for backward compatibility only.
 * New code should use the MemberBenefitService directly.
 */
class CalculateFreeHours
{
    use AsAction;

    public const HOURS_PER_DOLLAR_AMOUNT = 1; // Number of hours granted

    public const DOLLAR_AMOUNT_FOR_HOURS = 5; // Per this dollar amount

    /**
     * @deprecated Use MemberBenefitService::calculateFreeHours() instead
     */
    public function handle(float $contributionAmount): int
    {
        return app(MemberBenefitService::class)->calculateFreeHours($contributionAmount);
    }
}
