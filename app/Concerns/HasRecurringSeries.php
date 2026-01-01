<?php

namespace App\Concerns;

use App\Models\RecurringSeries;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that can be part of a recurring series.
 *
 * Models using this trait should have:
 * - recurring_series_id column (nullable foreign key)
 * - instance_date column (nullable date)
 */
trait HasRecurringSeries
{
    /**
     * Relationship to the recurring series this instance belongs to.
     */
    public function recurringSeries(): BelongsTo
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
    }

    /**
     * Check if this is part of a recurring series.
     */
    public function isRecurring(): bool
    {
        return $this->recurring_series_id !== null;
    }
}
