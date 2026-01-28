<?php

namespace CorvMC\Events\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * EventStatus tracks exceptional lifecycle states.
 *
 * Temporal states (upcoming, in-progress, past) are derived from start_time/end_time.
 * Publication state is tracked by published_at datetime.
 * Moderation state is tracked by moderation_status enum.
 * Reschedule tracking is via rescheduled_to_id column.
 */
enum EventStatus: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';
    case AtCapacity = 'at_capacity';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Postponed => 'Postponed',
            self::AtCapacity => 'At Capacity',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Scheduled => 'success',
            self::Cancelled => 'danger',
            self::Postponed => 'gray',
            self::AtCapacity => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Scheduled => 'tabler-calendar-check',
            self::Cancelled => 'tabler-calendar-x',
            self::Postponed => 'tabler-calendar-pause',
            self::AtCapacity => 'tabler-calendar-exclamation',
        };
    }

    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isPostponed(): bool
    {
        return $this === self::Postponed;
    }

    public function isAtCapacity(): bool
    {
        return $this === self::AtCapacity;
    }

    /**
     * Check if the event is actively happening (not cancelled/postponed).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::AtCapacity]);
    }
}
