<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\HasTimePeriod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Base class for all reservation types using Single Table Inheritance.
 * 
 * Reservations can be owned by different entities (User, Production, Band, etc.)
 * using a polymorphic relationship.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property ReservationStatus $status
 * @property PaymentStatus $payment_status
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
 * @property-read string $cost_display
 * @property-read float $duration
 * @property-read array $payment_status_badge
 * @property-read string $time_range
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read Model|\Eloquent|null $reservable
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reservation needsAttention()
 * @method static Builder<static>|Reservation newModelQuery()
 * @method static Builder<static>|Reservation newQuery()
 * @method static Builder<static>|Reservation query()
 * @method static Builder<static>|Reservation whereCancellationReason($value)
 * @method static Builder<static>|Reservation whereCost($value)
 * @method static Builder<static>|Reservation whereCreatedAt($value)
 * @method static Builder<static>|Reservation whereDeletedAt($value)
 * @method static Builder<static>|Reservation whereFreeHoursUsed($value)
 * @method static Builder<static>|Reservation whereGoogleCalendarEventId($value)
 * @method static Builder<static>|Reservation whereHoursUsed($value)
 * @method static Builder<static>|Reservation whereId($value)
 * @method static Builder<static>|Reservation whereInstanceDate($value)
 * @method static Builder<static>|Reservation whereIsRecurring($value)
 * @method static Builder<static>|Reservation whereNotes($value)
 * @method static Builder<static>|Reservation wherePaidAt($value)
 * @method static Builder<static>|Reservation wherePaymentMethod($value)
 * @method static Builder<static>|Reservation wherePaymentNotes($value)
 * @method static Builder<static>|Reservation wherePaymentStatus($value)
 * @method static Builder<static>|Reservation whereRecurrencePattern($value)
 * @method static Builder<static>|Reservation whereRecurringSeriesId($value)
 * @method static Builder<static>|Reservation whereReservableId($value)
 * @method static Builder<static>|Reservation whereReservableType($value)
 * @method static Builder<static>|Reservation whereReservedAt($value)
 * @method static Builder<static>|Reservation whereReservedUntil($value)
 * @method static Builder<static>|Reservation whereStatus($value)
 * @method static Builder<static>|Reservation whereType($value)
 * @method static Builder<static>|Reservation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Reservation extends Model
{
    use HasFactory, HasTimePeriod;

    protected $table = 'reservations';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'reserved_at' => 'datetime',
            'reserved_until' => 'datetime',
            'cost' => MoneyCast::class.':USD',
            'paid_at' => 'datetime',
            'hours_used' => 'decimal:2',
            'free_hours_used' => 'decimal:2',
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'array',
            'instance_date' => 'date',
        ];
    }

    /**
     * Boot method to handle STI type column and scoping.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = get_class($model);
            }
        });

        // Only add type scope for child classes, not the base Reservation class
        if (static::class !== self::class) {
            static::addGlobalScope('type', function (Builder $builder) {
                $builder->where('type', static::class);
            });
        }
    }

    /**
     * Create a new model instance from database results.
     * This enables Single Table Inheritance by instantiating the correct child class.
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (object) $attributes;
        // If we have a type attribute, instantiate that class instead
        if (isset($attributes->type) && $attributes->type !== static::class) {
            $class = $attributes->type;

            if (class_exists($class)) {
                $instance = new $class;
                $instance->exists = true;
                $instance->setRawAttributes((array) $attributes, true);
                $instance->setConnection($connection ?: $this->getConnectionName());
                $instance->fireModelEvent('retrieved', false);

                return $instance;
            }
        }

        return parent::newFromBuilder((array) $attributes, $connection);
    }

    /**
     * Polymorphic relationship to the owner of this reservation.
     * Can be User, Production, Band, etc.
     */
    public function reservable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Direct relationship to the user who created this reservation.
     * This is separate from reservable which represents who the reservation is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to the recurring series this reservation belongs to.
     */
    public function recurringSeries(): BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
    }

    /**
     * Check if this reservation is part of a recurring series.
     */
    public function isRecurring(): bool
    {
        return $this->recurring_series_id !== null;
    }

    /**
     * Get the human-readable label for this reservation type.
     * Child classes should override this method.
     */
    public function getReservationTypeLabel(): string
    {
        return 'Reservation';
    }

    /**
     * Get the icon identifier for this reservation type.
     * Child classes should override this method.
     */
    public function getReservationIcon(): string
    {
        return 'tabler-calendar';
    }

    /**
     * Get the display title for this reservation.
     * Child classes should override this method.
     */
    public function getDisplayTitle(): string
    {
        return 'Unknown';
    }

    /**
     * Get the user responsible for this reservation.
     * For practice reservations, this is the user who made it.
     * For production reservations, this is the production manager.
     * For band reservations, this could be the band primary contact.
     * Child classes should override this method.
     */
    public function getResponsibleUser(): ?User
    {
        return $this->reservable instanceof User ? $this->reservable : null;
    }

    /**
     * Payment status methods (only applicable to RehearsalReservation).
     * Base implementations return false/N/A for non-payment reservations.
     */
    public function isPaid(): bool
    {
        return false;
    }

    public function isComped(): bool
    {
        return false;
    }

    public function isUnpaid(): bool
    {
        return false;
    }

    public function isRefunded(): bool
    {
        return false;
    }

    public function getCostDisplayAttribute(): string
    {
        return 'N/A';
    }

    public function getDurationAttribute(): float
    {
        if (! $this->reserved_at || ! $this->reserved_until) {
            return 0;
        }

        return $this->reserved_at->diffInMinutes($this->reserved_until) / 60;
    }

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

    public function getPaymentStatusBadgeAttribute(): array
    {
        // Default implementation for non-payment reservations
        return [
            'label' => 'N/A',
            'color' => 'gray',
        ];
    }

    /**
     * Get the name of the start time field for this model.
     * Required by HasTimePeriod trait.
     */
    protected function getStartTimeField(): string
    {
        return 'reserved_at';
    }

    /**
     * Get the name of the end time field for this model.
     * Required by HasTimePeriod trait.
     */
    protected function getEndTimeField(): string
    {
        return 'reserved_until';
    }

    /**
     * Convert hours to credit blocks.
     * Rounds up to ensure users are charged for full blocks.
     */
    public static function hoursToBlocks(float $hours): int
    {
        $minutesPerBlock = config('reservation.minutes_per_block', 30);

        return (int) ceil(($hours * 60) / $minutesPerBlock);
    }

    /**
     * Convert credit blocks to hours.
     */
    public static function blocksToHours(int $blocks): float
    {
        $minutesPerBlock = config('reservation.minutes_per_block', 30);

        return ($blocks * $minutesPerBlock) / 60;
    }

    /**
     * Check if reservation is in the confirmation window (3-7 days before).
     */
    public function isInConfirmationWindow(): bool
    {
        if (! $this->reserved_at) {
            return false;
        }

        $daysUntilReservation = now()->diffInDays($this->reserved_at, false);

        return $daysUntilReservation >= 3 && $daysUntilReservation <= 7;
    }

    /**
     * Check if reservation is an immediate reservation (< 3 days).
     */
    public function isImmediate(): bool
    {
        if (! $this->reserved_at) {
            return false;
        }

        $daysUntilReservation = now()->diffInDays($this->reserved_at, false);

        return $daysUntilReservation < 3;
    }

    /**
     * Scope to filter reservations that need attention.
     * Includes pending reservations about to be autocancelled (< 3 days) and past unpaid reservations.
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($q) {
                // Pending reservations about to be autocancelled (< 3 days away)
                $q->where('status', 'pending')
                    ->where('reserved_at', '>', now())
                    ->where('reserved_at', '<=', now()->addDays(3));
            })->orWhere(function ($q) {
                // Past reservations that are unpaid
                $q->where('payment_status', 'unpaid')
                    ->where('cost', '>', 0)
                    ->where('reserved_at', '<', now());
            });
        })->where('status', '!=', 'cancelled');
    }

    /**
     * Check if reservation can be confirmed (pending and within confirmation window or immediate).
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === 'pending' &&
               ($this->isInConfirmationWindow() || $this->isImmediate());
    }

    /**
     * Check if reservation should be auto-cancelled (pending and missed confirmation window).
     */
    public function shouldAutoCancel(): bool
    {
        if ($this->status !== 'pending' || ! $this->reserved_at) {
            return false;
        }

        // Auto-cancel if we're now within 3 days and still not confirmed
        $daysUntilReservation = now()->diffInDays($this->reserved_at, false);

        return $daysUntilReservation < 3;
    }

    /**
     * Get days until confirmation deadline (3 days before reservation).
     */
    public function daysUntilConfirmationDeadline(): ?int
    {
        if (! $this->reserved_at) {
            return null;
        }

        $confirmationDeadline = $this->reserved_at->copy()->subDays(3);
        $daysUntil = now()->diffInDays($confirmationDeadline, false);

        return $daysUntil >= 0 ? $daysUntil : null;
    }

    public function requiresPayment(): bool
    {
        return $this->cost->isPositive() && $this->payment_status->isUnpaid();
    }
}
