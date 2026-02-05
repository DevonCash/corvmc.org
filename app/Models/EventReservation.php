<?php

namespace App\Models;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\Support\Concerns\HasTimePeriod;
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
 * @property \CorvMC\SpaceManagement\Enums\ReservationStatus $status
 * @property \CorvMC\Finance\Enums\PaymentStatus $payment_status
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
 * @property-read \CorvMC\Support\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $reservable
 * @property-read \App\Models\User|null $user
 *
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
 *
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
        return ReservationFactory::new()->setModelName(static::class);
    }

    // Note: type is set automatically by parent Reservation::creating callback using getMorphClass()

    /**
     * The event that owns this space reservation.
     */
    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->morphTo(__FUNCTION__, 'reservable_type', 'reservable_id');
    }

    // STI Abstract Method Implementations

    public function getLabel(): string
    {
        return 'Event';
    }

    public function getIcon(): string
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
}
