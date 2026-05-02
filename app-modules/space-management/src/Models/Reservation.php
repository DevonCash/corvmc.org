<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\States\ReservationState;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use CorvMC\Finance\Concerns\Purchasable;
use CorvMC\Support\Concerns\HasRecurringSeries;
use CorvMC\Support\Concerns\HasTimePeriod;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Watson\Validating\ValidatingTrait;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Activity;
use Spatie\ModelStates\HasStates;

class Reservation extends Model implements HasColor, HasIcon, HasLabel
{
    use ValidatingTrait, HasRecurringSeries, HasTimePeriod, HasStates, Purchasable;

    /**
     * Temporary store for old billable units between updating/updated hooks.
     */
    protected static array $pendingOldBillableUnits = [];

    public function getLockableFields(): array
    {
        return ['status', 'updated_at', 'cancelled_at', 'confirmed_at', 'completed_at'];
    }

    protected $table = 'reservations';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * Whether the model should throw a ValidationException if it
     * fails validation. If not set, it will default to false.
     */
    protected $throwValidationExceptions = true;

    /**
     * Base validation rules for all reservations.
     * 
     * @deprecated hours_used is auto-calculated and will be removed in future version
     */
    protected array $rules = [
        'reserved_at' => 'required|date',
        'reserved_until' => 'required|date|after:reserved_at',
        'reservable_type' => 'required|string',
        'reservable_id' => 'required|integer',
    ];

    /**
     * User exposed observable events for validation hooks.
     */
    protected $observables = ['validating', 'validated'];

    protected function casts(): array
    {
        return [
            'status' => ReservationState::class,
            'reserved_at' => 'datetime',
            'reserved_until' => 'datetime',
            'hours_used' => 'decimal:2', // @deprecated Use $this->duration from HasTimePeriod trait
            'free_hours_used' => 'decimal:2', // @deprecated Use Order LineItem discounts via Finance::findActiveOrder()
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'array',
            'instance_date' => 'date',
        ];
    }

    /**
     * Get the activity log entries for this reservation.
     *
     * Activity is logged via domain events (LogReservationActivity listener),
     * not via the LogsActivity trait.
     */
    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Boot method to handle STI type column and scoping.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-calculate hours if not set
            if (!$model->hours_used && $model->reserved_at && $model->reserved_until) {
                $model->hours_used = $model->reserved_at->diffInMinutes($model->reserved_until) / 60;
            }

            // Set type for STI
            if (empty($model->type)) {
                $model->type = $model->getMorphClass();
            }
        });

        static::updating(function ($model) {
            // Recalculate hours if times changed
            if ($model->isDirty(['reserved_at', 'reserved_until'])) {
                // Stash old billable units for the updated hook (not a model attribute)
                static::$pendingOldBillableUnits[$model->id ?? spl_object_id($model)] = (float) ($model->getOriginal('hours_used') ?? 0);
                $model->hours_used = $model->reserved_at->diffInMinutes($model->reserved_until) / 60;
            }
        });

        static::updated(function ($model) {
            // Fire rescheduled event when time fields changed
            $key = $model->id ?? spl_object_id($model);
            if (isset(static::$pendingOldBillableUnits[$key])) {
                $oldUnits = static::$pendingOldBillableUnits[$key];
                unset(static::$pendingOldBillableUnits[$key]);
                \CorvMC\SpaceManagement\Events\ReservationUpdated::dispatch($model, $oldUnits);
            }
        });

        // Only add type scope for child classes, not the base Reservation class
        if (static::class !== self::class) {
            static::addGlobalScope('type', function (Builder $builder) {
                $builder->where('type', (new static)->getMorphClass());
            });
        }

        // Add global scope to exclude cancelled reservations by default
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->whereNotIn('status', [Cancelled::class]);
        });
    }

    /**
     * Map type values to model classes.
     * Handles both morph aliases and legacy class names.
     */
    protected static array $typeMap = [
        // Morph aliases (current)
        'rehearsal_reservation' => RehearsalReservation::class,
        'event_reservation' => \App\Models\EventReservation::class,
        // Legacy class names (for backward compatibility)
        'App\Models\RehearsalReservation' => RehearsalReservation::class,
        'App\Models\EventReservation' => \App\Models\EventReservation::class,
        'CorvMC\SpaceManagement\Models\RehearsalReservation' => RehearsalReservation::class,
    ];

    /**
     * Create a new model instance from database results.
     * This enables Single Table Inheritance by instantiating the correct child class.
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (object) $attributes;
        // If we have a type attribute, instantiate that class instead
        if (isset($attributes->type) && $attributes->type !== (new static)->getMorphClass()) {
            // Map type to class (handles both morph aliases and legacy class names)
            $class = static::$typeMap[$attributes->type] ?? $attributes->type;

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
     * Query scope: Get upcoming reservations within a specified number of days.
     *
     * @param Builder $query
     * @param int $days Number of days to look ahead (default: 7)
     * @return Builder
     */
    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->with(['reservable'])
            ->where('reserved_at', '>=', now())
            ->where('reserved_at', '<=', now()->addDays($days))
            ->whereNotState('status', Cancelled::class)
            ->orderBy('reserved_at');
    }

    /**
     * Query scope: Get reservations for a specific user.
     * Note: This uses the 'reservable' polymorphic relationship.
     *
     * @param Builder $query
     * @param User $user
     * @param string|null $status Optional status filter
     * @return Builder
     */
    public function scopeForUser(Builder $query, User $user, ?string $status = null): Builder
    {
        $query = $query->where(function ($q) use ($user) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('reservable_type', User::class)
                    ->where('reservable_id', $user->id);
            });
            // Could add other conditions here if needed
        });

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with(['reservable'])
            ->orderBy('reserved_at', 'desc');
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
        $order = \CorvMC\Finance\Facades\Finance::findActiveOrder($this);

        if (! $order || $order->total_amount <= 0) {
            return 'Free';
        }

        return $order->formattedTotal();
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
     * HasTimePeriod configuration
     */
    protected static string $startTimeField = 'reserved_at';
    protected static string $endTimeField = 'reserved_until';

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
     * Check if this reservation requires payment.
     *
     * Override in subclasses that support pricing.
     */
    public function requiresPayment(): bool
    {
        return false;
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
                $q->whereState('status', ReservationState\Scheduled::class)
                    ->where('reserved_at', '>', now())
                    ->where('reserved_at', '<=', now()->addDays(3));
            })->orWhere(function ($q) {
                // Past reservations that are unpaid (check via charges table)
                $q->whereHas('charge', function ($chargeQuery) {
                    $chargeQuery->where('status', 'pending')
                        ->where('net_amount', '>', 0);
                })
                    ->where('reserved_at', '<', now());
            });
        })->where('status', '!=', 'cancelled');
    }

    protected ?bool $preloadedIsFirstReservation = null;

    /**
     * Set the precomputed "first reservation" flag to avoid N+1 queries.
     */
    public function setIsFirstReservation(bool $value): void
    {
        $this->preloadedIsFirstReservation = $value;
    }

    /**
     * Check if this is the first reservation for the responsible user.
     */
    public function isFirstReservationForUser(): bool
    {
        if ($this->preloadedIsFirstReservation !== null) {
            return $this->preloadedIsFirstReservation;
        }

        $user = $this->getResponsibleUser();

        if (! $user) {
            return false;
        }

        return ! $user->rehearsals()
            ->where('id', '!=', $this->id)
            ->whereNotState('status', ReservationState\Cancelled::class)
            ->exists();
    }


    /**
     * Check if this reservation can be confirmed.
     */
    public function canConfirm(): bool
    {
        return false; // By default, reservations cannot be confirmed. Override in subclasses that support confirmation.
    }

    /**
     * Check if this reservation needs a confirmation reminder.
     */
    public function needsConfirmationReminder(): bool
    {
        // Only scheduled reservations need reminders
        if ($this->status->isNot(ReservationState\Scheduled::class)) {
            return false;
        }

        // Check if it's within the reminder window (e.g., 2 days before)
        $reminderDate = $this->getConfirmationReminderDate();
        return now()->isAfter($reminderDate);
    }

    /**
     * Get the date when confirmation reminder should be sent.
     */
    public function getConfirmationReminderDate(): Carbon
    {
        // Send reminder 2 days before the reservation
        return $this->reserved_at->copy()->subDays(2)->startOfDay();
    }

    /**
     * Cancel the reservation with an optional reason.
     *
     * @param string|null $reason Cancellation reason
     * @return $this
     */
    public function cancel(?string $reason = null): self
    {
        // Transition to cancelled state
        $this->status->transitionTo(
            \CorvMC\SpaceManagement\States\ReservationState\Cancelled::class
        );

        // Save the state transition
        $this->save();

        // Update the cancellation reason field
        if ($reason) {
            $this->update(['cancellation_reason' => $reason]);
        }

        return $this->fresh();
    }

    /**
     * Confirm the reservation.
     *
     * @return $this
     */
    public function confirm(): self
    {
        $previousStatus = get_class($this->status);

        // Transition to confirmed state
        $this->status->transitionTo(\CorvMC\SpaceManagement\States\ReservationState\Confirmed::class);

        $this->refresh();

        \CorvMC\SpaceManagement\Events\ReservationConfirmed::dispatch($this, $previousStatus);

        return $this;
    }

    /**
     * Mark the reservation as complete.
     *
     * @return $this
     */
    public function complete(): self
    {
        // Transition to completed state
        $this->status->transitionTo(\CorvMC\SpaceManagement\States\ReservationState\Completed::class);

        return $this->fresh();
    }
}
