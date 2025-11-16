<?php

namespace App\Exceptions;

use Exception;

class SubscriptionException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function NotFound(string $message = 'Subscription not found', int $code = 404, ?Exception $previous = null): self
    {
        return new self($message, $code, $previous);
    }

    public static function PriceNotFound(string $message = 'Subscription price not found', int $code = 404, ?Exception $previous = null): self
    {
        return new self($message, $code, $previous);
    }
}
