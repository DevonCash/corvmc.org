<?php

namespace App\Actions\MemberBenefits;

use Lorisleiva\Actions\Concerns\AsAction;

class CheckQualifiesForBenefits
{
    use AsAction;

    public const SUSTAINING_MEMBER_THRESHOLD = 10.00;

    /**
     * Check if user qualifies for sustaining member benefits based on amount.
     *
     * Minimum threshold is $10/month.
     */
    public function handle(float $amount): bool
    {
        return $amount >= self::SUSTAINING_MEMBER_THRESHOLD;
    }
}
