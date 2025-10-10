<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class ToStripeAmount
{
    use AsAction;

    /**
     * Convert Money to cents for Stripe API
     */
    public function handle(Money $amount): int
    {
        return $amount->getMinorAmount()->toInt();
    }
}
