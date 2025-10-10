<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateTotalWithFeeCoverage
{
    use AsAction;

    /**
     * Calculate the total amount needed to cover both the base amount
     * and the processing fees. This accounts for the fee applying to itself.
     *
     * Formula: Total = (Base + Fixed Fee) / (1 - Rate)
     * This ensures that after Stripe takes their cut, we net the full base amount.
     */
    public function handle(Money $baseAmount): Money
    {
        $fixedFee = Money::ofMinor(CalculateProcessingFee::STRIPE_FIXED_FEE_CENTS, 'USD');
        $numerator = $baseAmount->plus($fixedFee);
        $denominator = 1 - CalculateProcessingFee::STRIPE_RATE;

        return $numerator->dividedBy($denominator, \Brick\Math\RoundingMode::HALF_UP);
    }
}
