<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;

/**
 * @deprecated Use FeeService::calculateNetAmount() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class CalculateNetAmount
{
    /**
     * @deprecated Use FeeService::calculateNetAmount() instead
     */
    public function handle(Money $totalCharged): Money
    {
        return app(FeeService::class)->calculateNetAmount($totalCharged);
    }
}
