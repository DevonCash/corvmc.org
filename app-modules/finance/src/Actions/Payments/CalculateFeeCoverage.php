<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateFeeCoverage
{
    use AsAction;

    /**
     * Calculate the fee coverage amount for a base amount in cents.
     */
    public function handle(int $baseAmountCents): Money
    {
        $baseAmount = Money::ofMinor($baseAmountCents, 'USD');
        $totalWithCoverage = CalculateTotalWithFeeCoverage::run($baseAmount);

        return $totalWithCoverage->minus($baseAmount);
    }
}
