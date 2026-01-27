<?php

namespace CorvMC\Events\Enums;

enum TicketOrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Refunded => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Check if this order is in a final state that cannot be changed.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Refunded, self::Cancelled]);
    }

    /**
     * Check if this order requires payment.
     */
    public function requiresPayment(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if this order can be refunded.
     */
    public function canRefund(): bool
    {
        return $this === self::Completed;
    }
}
