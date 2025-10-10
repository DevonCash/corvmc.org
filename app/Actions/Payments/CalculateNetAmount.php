<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateNetAmount
{
    use AsAction;

    /**
     * Calculate what amount we'll actually receive after Stripe processes a payment
     */
    public function handle(Money $totalCharged): Money
    {
        $processingFee = CalculateProcessingFee::run($totalCharged);
        return $totalCharged->minus($processingFee);
    }
}
