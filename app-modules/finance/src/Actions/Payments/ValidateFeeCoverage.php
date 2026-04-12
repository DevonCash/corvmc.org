<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use FeeService::validateFeeCoverage() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class ValidateFeeCoverage
{
    use AsAction;

    /**
     * @deprecated Use FeeService::validateFeeCoverage() instead
     */
    public function handle(Money $baseAmount, Money $totalCharged): bool
    {
        return app(FeeService::class)->validateFeeCoverage($baseAmount, $totalCharged);
    }
}
