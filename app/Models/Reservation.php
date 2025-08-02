<?php

namespace App\Models;

use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * Represents a reservation at the practice space.
 * It includes details about the user who made the reservation
 * and the status of the reservation.
 */
class Reservation extends Model implements Eventable
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'reserved_at',
        'reserved_until',
        'cost',
        'hours_used',
        'free_hours_used',
        'is_recurring',
        'recurrence_pattern',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
        'cost' => 'decimal:2',
        'hours_used' => 'decimal:2',
        'free_hours_used' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reservation as a Period object.
     */
    public function getPeriod(): ?Period
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return null;
        }

        return Period::make(
            $this->reserved_at,
            $this->reserved_until,
            Precision::MINUTE()
        );
    }

    /**
     * Check if this reservation overlaps with another period.
     */
    public function overlapsWith(Period $period): bool
    {
        $thisPeriod = $this->getPeriod();

        if (! $thisPeriod) {
            return false;
        }

        return $thisPeriod->overlapsWith($period);
    }

    /**
     * Check if this reservation touches another period (adjacent periods).
     */
    public function touchesWith(Period $period): bool
    {
        $thisPeriod = $this->getPeriod();

        if (! $thisPeriod) {
            return false;
        }

        return $thisPeriod->touchesWith($period);
    }

    /**
     * Get the duration of the reservation in hours.
     */
    public function getDurationAttribute(): float
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get a formatted time range for display.
     */
    public function getTimeRangeAttribute(): string
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 'TBD';
        }

        if ($this->reserved_at->isSameDay($this->reserved_until)) {
            return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('g:i A');
        }

        return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('M j, Y g:i A');
    }

    /**
     * Check if reservation is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if reservation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if reservation is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if reservation is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->reserved_at && $this->reserved_at->isFuture();
    }

    /**
     * Check if reservation is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->reserved_at && $this->reserved_until &&
            $this->reserved_at->isPast() && $this->reserved_until->isFuture();
    }

    /**
     * Get formatted cost display.
     */
    public function getCostDisplayAttribute(): string
    {
        if ($this->cost == 0) {
            return 'Free';
        }

        return '$' . number_format($this->cost, 2);
    }

    /**
     * Convert reservation to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        $currentUser = User::me();
        $isOwnReservation = $currentUser && $currentUser->id === $this->user_id;
        $canViewDetails = $currentUser && $currentUser->can('view reservations');

        // Show full details for own reservations or if user has permission
        if ($isOwnReservation || $canViewDetails) {
            $title = $this->user->name;

            if ($this->is_recurring) {
                $title .= ' (Recurring)';
            }

            if ($this->status === 'pending') {
                $title .= ' (Pending)';
            }
        } else {
            // Show limited info for other users' reservations
            $title = 'Reserved';

            if ($this->status === 'pending') {
                $title .= ' (Pending)';
            }
        }

        $color = match ($this->status) {
            'confirmed' => '#10b981', // green
            'pending' => '#f59e0b',   // yellow
            'cancelled' => '#ef4444', // red
            default => '#6b7280',     // gray
        };

        $extendedProps = [
            'type' => 'reservation',
            'status' => $this->status,
            'duration' => $this->duration,
            'is_recurring' => $this->is_recurring,
        ];

        // Add detailed info only for own reservations or if permitted
        if ($isOwnReservation || $canViewDetails) {
            $extendedProps['user_name'] = $this->user->name;
            $extendedProps['cost'] = $this->cost;
        }

        return CalendarEvent::make($this)
            ->model(static::class)
            ->key($this->id)
            ->title($title)
            ->start($this->reserved_at)
            ->end($this->reserved_until)
            ->backgroundColor($color)
            ->textColor('#fff')
            ->extendedProps($extendedProps);
    }
}
