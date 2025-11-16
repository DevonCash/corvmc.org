<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Recurring Series Pattern
 *
 * Represents a recurring series (e.g., "Every Tuesday 7-9pm").
 * Can create instances of Reservations or Events.
 * Individual instances are stored in their respective tables.
 */
class RecurringSeries extends Model
{
    use LogsActivity;

    protected $table = 'recurring_series';

    protected $fillable = [
        'user_id',
        'recurable_type',
        'recurrence_rule',
        'start_time',
        'end_time',
        'duration_minutes',
        'series_start_date',
        'series_end_date',
        'max_advance_days',
        'status',
        'notes',
    ];

    protected $casts = [
        'series_start_date' => 'date',
        'series_end_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'duration_minutes' => 'integer',
        'max_advance_days' => 'integer',
    ];

    /**
     * User who owns this recurring series
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All instances for this series (Reservations or Events based on recurable_type)
     */
    public function instances(): HasMany
    {
        if ($this->recurable_type === Reservation::class) {
            return $this->hasMany(Reservation::class, 'recurring_series_id');
        }

        return $this->hasMany(Event::class, 'recurring_series_id');
    }

    /**
     * Only upcoming instances
     */
    public function upcomingInstances(): HasMany
    {
        return $this->instances()
            ->where('instance_date', '>=', now()->toDateString())
            ->orderBy('instance_date');
    }

    /**
     * Only active (confirmed/pending/approved) instances
     */
    public function activeInstances(): HasMany
    {
        if ($this->recurable_type === Reservation::class) {
            return $this->instances()
                ->whereIn('status', ['pending', 'confirmed']);
        }

        return $this->instances()
            ->where('status', 'approved');
    }

    /**
     * Check if this series creates Reservations
     */
    public function isReservationSeries(): bool
    {
        return $this->recurable_type === Reservation::class;
    }

    /**
     * Check if this series creates Events
     */
    public function isEventSeries(): bool
    {
        return $this->recurable_type === Event::class;
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'series_end_date', 'recurrence_rule', 'recurable_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
