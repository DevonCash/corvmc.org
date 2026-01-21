<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
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
 * @property \CorvMC\SpaceManagement\Enums\PaymentStatus $payment_status
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

    protected $attributes = [
        'type' => self::class,
    ];

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
