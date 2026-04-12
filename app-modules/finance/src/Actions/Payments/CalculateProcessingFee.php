<?php

namespace CorvMC\Finance\Actions\Payments;

use Brick\Money\Money;
use CorvMC\Finance\Services\FeeService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use FeeService::calculateProcessingFee() instead
 * This action is maintained for backward compatibility only.
 * New code should use the FeeService directly.
 */
class CalculateProcessingFee
{
    use AsAction;

    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    const STRIPE_RATE = 0.029;

    const STRIPE_FIXED_FEE_CENTS = 30;

    /**
     * @deprecated Use FeeService::calculateProcessingFee() instead
     */
    public function handle(Money $baseAmount): Money
    {
        return app(FeeService::class)->calculateProcessingFee($baseAmount);
    }
}
