<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Refunded => 'Refunded',
        };
    }
}
