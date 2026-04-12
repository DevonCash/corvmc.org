<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;

/**
 * @deprecated Use FeeService::calculateTotalWithFeeCoverage() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class CalculateTotalWithFeeCoverage
{
    /**
     * @deprecated Use FeeService::calculateTotalWithFeeCoverage() instead
     */
    public function handle(Money $baseAmount): Money
    {
        return app(FeeService::class)->calculateTotalWithFeeCoverage($baseAmount);
    }
}
