<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\HasTimePeriod;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Period\Period;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a practice space reservation made by an individual user.
 */
class RehearsalReservation extends Reservation implements Eventable
{
    use HasFactory, LogsActivity, HasTimePeriod;

    // Table and primary key are inherited from parent

    /**
     * Get the name of the start time field for this model.
     */
    protected function getStartTimeField(): string
    {
        return 'reserved_at';
    }

    /**
     * Get the name of the end time field for this model.
     */
    protected function getEndTimeField(): string
    {
        return 'reserved_until';
    }

    // Guarded is inherited from parent - using $guarded = ['id'] from Reservation

    protected $attributes = [
        'payment_status' => 'unpaid',
    ];

    // Casts are inherited from parent Reservation class

    /**
     * The user who made this practice reservation.
     */
    public function user()
    {
        return $this->morphTo(__FUNCTION__, 'reservable_type', 'reservable_id');
    }

    /**
     * Recurring reservation series this is an instance of (if applicable)
     */
    public function recurringSeries()
    {
        return $this->belongsTo(RecurringReservation::class, 'recurring_reservation_id');
    }

    // STI Abstract Method Implementations

    public function getReservationTypeLabel(): string
    {
        return 'Practice Space';
    }

    public function getReservationIcon(): string
    {
        return 'tabler-calendar';
    }

    public function getDisplayTitle(): string
    {
        return $this->reservable?->name ?? 'Unknown User';
    }

    public function getResponsibleUser(): ?User
    {
        return $this->reservable;
    }

    // Existing Methods from Original Reservation Model

    /**
     * Get the reservation as a Period object.
     *
     * @deprecated Use createPeriod() instead
     */
    public function getPeriod(): ?Period
    {
        return $this->createPeriod();
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
        if ($this->cost->isZero()) {
            return 'Free';
        }

        return $this->cost->formatTo('en_US');
    }

    /**
     * Get formatted status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status ?? '');
    }

    /**
     * Check if reservation is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if reservation is comped.
     */
    public function isComped(): bool
    {
        return $this->payment_status === 'comped';
    }

    /**
     * Check if reservation is unpaid.
     */
    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    /**
     * Check if reservation is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    /**
     * Mark reservation as paid.
     */
    public function markAsPaid(?string $paymentMethod = null, ?string $notes = null): void
    {
        \App\Actions\Payments\MarkReservationAsPaid::run($this, $paymentMethod, $notes);
    }

    /**
     * Mark reservation as comped.
     */
    public function markAsComped(?string $notes = null): void
    {
        \App\Actions\Payments\MarkReservationAsComped::run($this, $notes);
    }

    /**
     * Mark reservation as refunded.
     */
    public function markAsRefunded(?string $notes = null): void
    {
        \App\Actions\Payments\MarkReservationAsRefunded::run($this, $notes);
    }

    public function markAsCancelled()
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Get payment status display with badge styling.
     */
    public function getPaymentStatusBadgeAttribute(): array
    {
        return \App\Actions\Payments\GetPaymentStatusBadge::run($this);
    }

    /**
     * Convert reservation to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return \App\Actions\Calendar\ReservationToCalendarEvent::run($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reserved_at', 'reserved_until', 'cost', 'payment_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Practice space reservation {$eventName}");
    }
}
