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
 *
 * @property int $id
 * @property int $user_id
 * @property string $recurrence_rule
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property int $duration_minutes
 * @property \Illuminate\Support\Carbon $series_start_date
 * @property \Illuminate\Support\Carbon|null $series_end_date
 * @property int $max_advance_days
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $recurable_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event> $activeInstances
 * @property-read int|null $active_instances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event> $instances
 * @property-read int|null $instances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event> $upcomingInstances
 * @property-read int|null $upcoming_instances_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereMaxAdvanceDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereRecurableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereRecurrenceRule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereSeriesEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereSeriesStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringSeries whereUserId($value)
 *
 * @mixin \Eloquent
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
        'status' => \App\Enums\RecurringSeriesStatus::class,
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
