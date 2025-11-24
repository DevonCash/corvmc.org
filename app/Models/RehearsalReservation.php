<?php

namespace App\Models;

use App\Concerns\HasTimePeriod;
use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Period\Period;

/**
 * Represents a practice space reservation made by an individual user.
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string $cost_display
 * @property-read float $duration
 * @property-read array $payment_status_badge
 * @property-read string $status_display
 * @property-read string $time_range
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $reservable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $user
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|RehearsalReservation needsAttention()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereFreeHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereGoogleCalendarEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereInstanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereRecurrencePattern($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereRecurringSeriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereReservedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RehearsalReservation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RehearsalReservation extends Reservation
{
    use HasFactory, HasTimePeriod, LogsActivity;

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

    protected $attributes = [
        'payment_status' => 'unpaid',
    ];

    // Casts are inherited from parent Reservation class

    /**
     * The user who made this practice reservation.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reservable_type', 'reservable_id');
    }

    /**
     * Recurring reservation series this is an instance of (if applicable)
     */
    public function recurringSeries(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reserved_at', 'reserved_until', 'cost', 'payment_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Practice space reservation {$eventName}");
    }

    #[Scope]
    public function upcoming(Builder $query)
    {
        $query->where('reserved_at', '>', now())
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('status', '!=', ReservationStatus::Completed);
    }
}
