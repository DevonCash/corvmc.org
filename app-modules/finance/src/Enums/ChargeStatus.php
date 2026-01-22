<?php

namespace CorvMC\Finance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ChargeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Comped = 'comped';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Comped => 'Comped',
            self::Refunded => 'Refunded',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'tabler-clock-dollar',
            self::Paid => 'tabler-coin',
            self::Comped => 'tabler-gift',
            self::Refunded => 'tabler-receipt-refund',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Comped => 'info',
            self::Refunded => 'gray',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
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

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Comped, self::Refunded], true);
    }

    public function requiresPayment(): bool
    {
        return $this === self::Pending;
    }
}
