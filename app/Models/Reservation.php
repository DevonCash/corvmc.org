<?php

namespace App\Models;

use App\Casts\MoneyCast;
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
    use HasFactory;

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
     * Boot method to handle STI type column.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = get_class($model);
            }
        });
    }

    /**
     * Create a new model instance from database results.
     * This enables Single Table Inheritance by instantiating the correct child class.
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
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

        return parent::newFromBuilder($attributes, $connection);
    }

    /**
     * Scope queries to this reservation type automatically.
     * Only applies to child classes, not the base Reservation class.
     */
    public function newQuery(): Builder
    {
        $query = parent::newQuery();

        // Only apply type filter for child classes, not base Reservation
        if (static::class !== self::class) {
            $query->where('type', static::class);
        }

        return $query;
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
}
