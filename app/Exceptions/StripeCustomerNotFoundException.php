<?php

namespace App\Exceptions;

class StripeCustomerNotFoundException extends SubscriptionException
{
    public function __construct(string $message = 'No Stripe customer found')
    {
        parent::__construct($message);
    }
}