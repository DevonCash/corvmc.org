<?php

namespace App\Models;

use App\Concerns\HasTimePeriod;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a space reservation for an event.
 * 
 * Owned by an Event, includes setup and breakdown time.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property \App\Enums\ReservationStatus $status
 * @property \App\Enums\PaymentStatus $payment_status
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $payment_notes
 * @property numeric $hours_used
 * @property numeric $free_hours_used
 * @property bool $is_recurring
 * @property array<array-key, mixed>|null $recurrence_pattern
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $reserved_at
 * @property \Illuminate\Support\Carbon|null $reserved_until
 * @property \Brick\Money\Money $cost
 * @property int|null $recurring_series_id
 * @property \Illuminate\Support\Carbon|null $instance_date
 * @property string|null $cancellation_reason
 * @property string $type
 * @property string|null $reservable_type
 * @property int|null $reservable_id
 * @property string|null $google_calendar_event_id
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $event
 * @property-read float $breakdown_time
 * @property-read string $cost_display
 * @property-read float $duration
 * @property-read array $payment_status_badge
 * @property-read float $setup_time
 * @property-read string $status_display
 * @property-read string $time_range
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $reservable
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventReservation needsAttention()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereFreeHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereGoogleCalendarEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereInstanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation wherePaymentNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereRecurrencePattern($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereRecurringSeriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereReservableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereReservableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereReservedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReservation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class EventReservation extends Reservation
{
    use HasFactory, HasTimePeriod;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ReservationFactory::new();
    }

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
    // Casts are inherited from parent Reservation class

    /**
     * The event that owns this space reservation.
     */
    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->morphTo(__FUNCTION__, 'reservable_type', 'reservable_id');
    }

    // STI Abstract Method Implementations

    public function getReservationTypeLabel(): string
    {
        return 'Event';
    }

    public function getReservationIcon(): string
    {
        return 'tabler-calendar-event';
    }

    public function getDisplayTitle(): string
    {
        return $this->reservable?->title ?? 'Unknown Event';
    }

    public function getResponsibleUser(): ?User
    {
        return $this->reservable?->organizer;
    }

    // Event-Specific Methods

    /**
     * Get the duration of the space reservation in hours.
     */
    public function getDurationAttribute(): float
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

    /**
     * Get setup time in hours (before event start).
     */
    public function getSetupTimeAttribute(): float
    {
        if (! $this->reserved_at || ! $this->event?->start_time) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->event->start_time) / 60;
    }

    /**
     * Get breakdown time in hours (after event end).
     */
    public function getBreakdownTimeAttribute(): float
    {
        if (! $this->reserved_until || ! $this->event?->end_time) {
            return 0;
        }

        return $this->event->end_time->diffInMinutes($this->reserved_until) / 60;
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
            return $this->reserved_at->format('M j, Y g:i A').' - '.$this->reserved_until->format('g:i A');
        }

        return $this->reserved_at->format('M j, Y g:i A').' - '.$this->reserved_until->format('M j, Y g:i A');
    }

    /**
     * Check if reservation is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
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
     * Event reservations don't have payment tracking.
     * Payment is handled separately via tickets.
     */
    public function getCostDisplayAttribute(): string
    {
        return 'N/A';
    }

    /**
     * Get formatted status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status ?? '');
    }
}
