<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\HasPaymentStatus;
use App\Concerns\HasRecurringSeries;
use App\Concerns\HasTimePeriod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
 *
 * @method static \Database\Factories\ReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Reservation needsAttention()
 * @method static Builder<static>|Reservation status(ReservationStatus $status)
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
 *
 * @mixin \Eloquent
 */
class Reservation extends Model implements HasColor, HasIcon, HasLabel
{
    use HasPaymentStatus, HasRecurringSeries, HasTimePeriod, LogsActivity;

    protected $table = 'reservations';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName(static::getLabel())
            ->setDescriptionForEvent(fn (string $eventName) => "Reservation has been {$eventName}");
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
        throw new \RuntimeException('getDisplayTitle() not implemented in subclass of Reservation');
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

    public function getCostDisplayAttribute(): string
    {
        return 'N/A';
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

    public function getColor(): string|array
    {
        return 'gray';
    }

    public function getLabel(): string
    {
        return 'Reservation';
    }

    public function getIcon(): string
    {
        return 'tabler-calendar';
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

    public function requiresPayment(): bool
    {
        return $this->status->isActive()
            && $this->cost->isPositive()
            && $this->payment_status->isUnpaid();
    }

    /**
     * Scope to filter reservations that need attention.
     * Includes scheduled reservations about to be autocancelled (< 3 days) and past unpaid reservations.
     */
    #[Scope]
    protected function needsAttention(Builder $query)
    {
        $query->where(function ($q) {
            $q->where(function ($q) {
                // Scheduled reservations about to be autocancelled (< 3 days away)
                $q->where('status', ReservationStatus::Scheduled)
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

    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('reserved_at', '>', now())
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('status', '!=', ReservationStatus::Completed);
    }

    #[Scope]
    protected function status(Builder $query, ReservationStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Check if this is the first reservation for the responsible user.
     */
    public function isFirstReservationForUser(): bool
    {
        $user = $this->getResponsibleUser();

        if (! $user) {
            return false;
        }

        return ! self::query()
            ->where('reservable_type', User::class)
            ->where('reservable_id', $user->id)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
