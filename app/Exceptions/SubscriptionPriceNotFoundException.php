<?php

namespace App\Exceptions;

class SubscriptionPriceNotFoundException extends SubscriptionException
{
    public function __construct(int $amount, bool $isFeeCoverage = false)
    {
        $type = $isFeeCoverage ? 'fee coverage' : 'base';
        $message = "Stripe price for {$type} amount \${$amount} not found. Please run: php artisan subscription:create-prices";

        parent::__construct($message);
    }
}
