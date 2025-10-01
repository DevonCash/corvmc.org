<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Recurring Reservation Pattern
 *
 * Represents a recurring reservation series (e.g., "Every Tuesday 7-9pm").
 * Individual reservation instances are stored in the reservations table.
 */
class RecurringReservation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
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
     * User who owns this recurring reservation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All reservation instances for this series
     */
    public function instances(): HasMany
    {
        return $this->hasMany(Reservation::class, 'recurring_reservation_id');
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
     * Only active (confirmed/pending) instances
     */
    public function activeInstances(): HasMany
    {
        return $this->instances()
            ->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'series_end_date', 'recurrence_rule'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
