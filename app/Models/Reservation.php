<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\HasTimePeriod;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base class for all reservation types using Single Table Inheritance.
 *
 * Reservations can be owned by different entities (User, Production, Band, etc.)
 * using a polymorphic relationship.
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
            'reserved_at' => 'datetime',
            'reserved_until' => 'datetime',
            'cost' => MoneyCast::class . ':USD',
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
    public function reservable()
    {
        return $this->morphTo();
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
            return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('g:i A');
        }

        return $this->reserved_at->format('M j, Y g:i A') . ' - ' . $this->reserved_until->format('M j, Y g:i A');
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
        if (!$this->reserved_at) {
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
        if (!$this->reserved_at) {
            return false;
        }

        $daysUntilReservation = now()->diffInDays($this->reserved_at, false);
        return $daysUntilReservation < 3;
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
        if ($this->status !== 'pending' || !$this->reserved_at) {
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
        if (!$this->reserved_at) {
            return null;
        }

        $confirmationDeadline = $this->reserved_at->copy()->subDays(3);
        $daysUntil = now()->diffInDays($confirmationDeadline, false);

        return $daysUntil >= 0 ? $daysUntil : null;
    }
}
