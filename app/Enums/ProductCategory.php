<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Concession = 'concession';
    case Merchandise = 'merchandise';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Concession => 'Concession',
            self::Merchandise => 'Merchandise',
            self::Other => 'Other',
        };
    }
}
