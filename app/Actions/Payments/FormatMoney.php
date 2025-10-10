<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class FormatMoney
{
    use AsAction;

    /**
     * Format a money amount for display
     */
    public function handle(Money $amount): string
    {
        return $amount->formatTo('en_US');
    }
}
