<?php

namespace CorvMC\Finance\Exceptions;

class SubscriptionNotFoundException extends SubscriptionException
{
    public function __construct(string $message = 'No active subscription found')
    {
        parent::__construct($message);
    }
}
