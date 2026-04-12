<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;

/**
 * @deprecated Use FeeService::getFeeDisplayInfo() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class GetFeeDisplayInfo
{
    /**
     * @deprecated Use FeeService::getFeeDisplayInfo() instead
     */
    public function handle(Money $baseAmount): array
    {
        return app(FeeService::class)->getFeeDisplayInfo($baseAmount);
    }
}
