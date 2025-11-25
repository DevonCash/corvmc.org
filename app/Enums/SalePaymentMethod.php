<?php

namespace App\Enums;

enum SalePaymentMethod: string
{
    case Cash = 'cash';
    case CardOnFile = 'card_on_file';
    case CardKiosk = 'card_kiosk';
    case CustomerBalance = 'customer_balance';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::CardOnFile => 'Card on File',
            self::CardKiosk => 'Card at Kiosk',
            self::CustomerBalance => 'Customer Balance',
        };
    }
}
