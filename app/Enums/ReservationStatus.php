<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'pending';
    case Reserved = 'reserved';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Reserved => 'Reserved',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Confirmed => 'tabler-calendar-check',
            self::Scheduled => 'tabler-calendar-event',
            self::Reserved => 'tabler-hourglass',
            self::Cancelled => 'tabler-calendar-cancel',
            self::Completed => 'tabler-checkbox',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Cancelled => 'danger',
            self::Scheduled => 'info',
            self::Reserved => 'warning',
            self::Confirmed, self::Completed => 'success',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Completed]);
    }

    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
    }

    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }

    public function isReserved(): bool
    {
        return $this === self::Reserved;
    }
}
