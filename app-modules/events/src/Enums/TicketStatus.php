<?php

namespace CorvMC\Events\Enums;

enum TicketStatus: string
{
    case Valid = 'valid';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::CheckedIn => 'Checked In',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::CheckedIn => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Check if this ticket can be used for entry.
     */
    public function canCheckIn(): bool
    {
        return $this === self::Valid;
    }
}
