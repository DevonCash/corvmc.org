<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;

/**
 * @deprecated Use FeeService::calculateFeeCoverage() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class CalculateFeeCoverage
{
    /**
     * @deprecated Use FeeService::calculateFeeCoverage() instead
     */
    public function handle(int $baseAmountCents): Money
    {
        return app(FeeService::class)->calculateFeeCoverage($baseAmountCents);
    }
}
