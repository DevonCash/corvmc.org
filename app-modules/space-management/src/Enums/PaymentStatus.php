<?php

namespace CorvMC\SpaceManagement\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Comped = 'comped';
    case Refunded = 'refunded';
    case NotApplicable = 'n/a';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid',
            self::Comped => 'Comped',
            self::Refunded => 'Refunded',
            self::NotApplicable => 'N/A',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Paid => 'tabler-coin',
            self::Comped => 'tabler-gift',
            self::Refunded => 'tabler-receipt-refund',
            self::Unpaid => 'tabler-clock-dollar',
            self::NotApplicable => 'tabler-coin-off',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Paid => 'success',
            self::Comped => 'info',
            self::Refunded => 'gray',
            self::Unpaid => 'danger',
            self::NotApplicable => 'gray',
        };
    }

    public function requiresPayment(): bool
    {
        return $this === self::Unpaid;
    }

    public function isUnpaid(): bool
    {
        return $this === self::Unpaid;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isComped(): bool
    {
        return $this === self::Comped;
    }

    public function isRefunded(): bool
    {
        return $this === self::Refunded;
    }
}
