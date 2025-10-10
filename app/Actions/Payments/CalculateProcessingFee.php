<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateProcessingFee
{
    use AsAction;

    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    const STRIPE_RATE = 0.029;
    const STRIPE_FIXED_FEE_CENTS = 30;

    /**
     * Calculate the processing fee for a given Money amount.
     */
    public function handle(Money $baseAmount): Money
    {
        $percentageFee = $baseAmount->multipliedBy(self::STRIPE_RATE, \Brick\Math\RoundingMode::HALF_UP);
        $fixedFee = Money::ofMinor(self::STRIPE_FIXED_FEE_CENTS, 'USD');

        return $percentageFee->plus($fixedFee);
    }
}
