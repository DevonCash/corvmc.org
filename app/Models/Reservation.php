<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a reservation at the practice space.
 *
 * It includes details about the user who made the reservation
 * and the status of the reservation.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int $user_id
 * @property string $status
 * @property string $payment_status
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string $cost_display
 * @property-read float $duration
 * @property-read array $payment_status_badge
 * @property-read string $status_display
 * @property-read string $time_range
 * @property-read \App\Models\Transaction|null $paymentTransaction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereFreeHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereHoursUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation wherePaymentNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereRecurrencePattern($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereReservedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereUserId($value)
 * @mixin \Eloquent
 */
class Reservation extends Model implements Eventable
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'status',
        'reserved_at',
        'reserved_until',
        'cost',
        'payment_status',
        'payment_method',
        'paid_at',
        'payment_notes',
        'hours_used',
        'free_hours_used',
        'is_recurring',
        'recurrence_pattern',
        'notes',
    ];

    protected $attributes = [
        'payment_status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'reserved_until' => 'datetime',
            'cost' => MoneyCast::class . ':USD',
            'paid_at' => 'datetime',
            'hours_used' => 'decimal:2',
            'free_hours_used' => 'decimal:2',
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reservation's transactions.
     */
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Get the payment transaction for this reservation.
     */
    public function paymentTransaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', 'payment');
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
        if ($this->cost->isZero()) {
            return 'Free';
        }

        return '$' . number_format($this->cost->getAmount()->toFloat(), 2);
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
        return \App\Facades\PaymentService::isReservationPaid($this);
    }

    /**
     * Check if reservation is comped.
     */
    public function isComped(): bool
    {
        return \App\Facades\PaymentService::isReservationComped($this);
    }

    /**
     * Check if reservation is unpaid.
     */
    public function isUnpaid(): bool
    {
        return \App\Facades\PaymentService::isReservationUnpaid($this);
    }

    /**
     * Check if reservation is refunded.
     */
    public function isRefunded(): bool
    {
        return \App\Facades\PaymentService::isReservationRefunded($this);
    }

    /**
     * Mark reservation as paid.
     */
    public function markAsPaid(?string $paymentMethod = null, ?string $notes = null): void
    {
        \App\Facades\PaymentService::markReservationAsPaid($this, $paymentMethod, $notes);
    }

    /**
     * Mark reservation as comped.
     */
    public function markAsComped(?string $notes = null): void
    {
        \App\Facades\PaymentService::markReservationAsComped($this, $notes);
    }

    /**
     * Mark reservation as refunded.
     */
    public function markAsRefunded(?string $notes = null): void
    {
        \App\Facades\PaymentService::markReservationAsRefunded($this, $notes);
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
        return \App\Facades\PaymentService::getPaymentStatusBadge($this);
    }

    /**
     * Convert reservation to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return \App\Facades\CalendarService::reservationToCalendarEvent($this);
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
