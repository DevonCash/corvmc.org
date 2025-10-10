<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateFeeCoverage
{
    use AsAction;

    /**
     * Validate that fee coverage actually results in the base amount being received
     */
    public function handle(Money $baseAmount, Money $totalCharged): bool
    {
        $netReceived = CalculateNetAmount::run($totalCharged);
        $tolerance = Money::ofMinor(1, 'USD'); // 1 cent tolerance for rounding

        return $netReceived->minus($baseAmount)->abs()->isLessThanOrEqualTo($tolerance);
    }
}
