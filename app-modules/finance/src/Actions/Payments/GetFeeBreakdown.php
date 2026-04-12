<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use FeeService::getFeeBreakdown() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class GetFeeBreakdown
{
    use AsAction;

    /**
     * @deprecated Use FeeService::getFeeBreakdown() instead
     */
    public function handle(Money $baseAmount, bool $coverFees = false): array
    {
        return app(FeeService::class)->getFeeBreakdown($baseAmount, $coverFees);
    }
}
