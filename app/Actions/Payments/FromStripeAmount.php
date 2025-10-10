<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class FromStripeAmount
{
    use AsAction;

    /**
     * Convert cents from Stripe API to Money
     */
    public function handle(int $cents): Money
    {
        return Money::ofMinor($cents, 'USD');
    }
}
