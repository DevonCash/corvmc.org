<?php

namespace App\Models;

/**
 * Recurring Reservation Pattern (DEPRECATED - Use RecurringSeries instead)
 *
 * This class is kept for backwards compatibility.
 * All new code should use RecurringSeries with recurable_type = Reservation::class
 *
 * @deprecated Use RecurringSeries instead
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
