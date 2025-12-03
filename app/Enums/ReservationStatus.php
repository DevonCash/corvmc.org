<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Confirmed => 'tabler-clock-check',
            self::Scheduled => 'tabler-calendar-check',
            self::Cancelled => 'tabler-clock-x',
            self::Completed => 'tabler-circle-check',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Cancelled => 'danger',
            self::Scheduled => 'info',
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
}
