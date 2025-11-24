<?php

namespace App\Models;

/**
 * Recurring Reservation Pattern (DEPRECATED - Use RecurringSeries instead)
 * 
 * This class is kept for backwards compatibility.
 * All new code should use RecurringSeries with recurable_type = Reservation::class
 *
 * @deprecated Use RecurringSeries instead
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservation> $activeInstances
 * @property-read int|null $active_instances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservation> $instances
 * @property-read int|null $instances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservation> $upcomingInstances
 * @property-read int|null $upcoming_instances_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereMaxAdvanceDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereRecurableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereRecurrenceRule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereSeriesEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereSeriesStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringReservation whereUserId($value)
 * @mixin \Eloquent
 */
class RecurringReservation extends RecurringSeries
{
    protected static function boot()
    {
        parent::boot();

        // Automatically set recurable_type to Reservation when using this class
        static::creating(function ($model) {
            $model->recurable_type = Reservation::class;
        });
    }

    /**
     * All reservation instances for this series
     */
    public function instances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class, 'recurring_series_id');
    }
}
