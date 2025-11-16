<?php

namespace App\Actions\MemberBenefits;

use Lorisleiva\Actions\Concerns\AsAction;

class CalculateFreeHours
{
    use AsAction;

    public const HOURS_PER_DOLLAR_AMOUNT = 1; // Number of hours granted

    public const DOLLAR_AMOUNT_FOR_HOURS = 5; // Per this dollar amount

    /**
     * Calculate free hours based on contribution amount.
     *
     * Formula: 1 hour per $5 contributed
     * Example: $25/month = 5 hours, $50/month = 10 hours
     */
    public function handle(float $contributionAmount): int
    {
        return intval(floor($contributionAmount / self::DOLLAR_AMOUNT_FOR_HOURS) * self::HOURS_PER_DOLLAR_AMOUNT);
    }
}
