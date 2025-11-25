<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Check-ins are always tied to a specific reservation, shift, or event.
 * They track when a member arrives and leaves for that activity.
 *
 * @property int $id
 * @property int $user_id
 * @property string $checkable_type
 * @property int $checkable_id
 * @property \Carbon\Carbon $checked_in_at
 * @property \Carbon\Carbon|null $checked_out_at
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read Model $checkable
 */
class CheckIn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'checkable_type',
        'checkable_id',
        'checked_in_at',
        'checked_out_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * The user who checked in
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The thing being checked in for (Reservation, VolunteerShift, etc.)
     */
    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'checkable_type', 'checkable_id', 'checked_in_at', 'checked_out_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if this check-in has been checked out
     */
    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    /**
     * Get the duration of this check-in in minutes
     */
    public function duration(): ?int
    {
        if (! $this->isCheckedOut()) {
            return null;
        }

        return $this->checked_in_at->diffInMinutes($this->checked_out_at);
    }

    /**
     * Scope to only currently checked-in (not checked out)
     */
    public function scopeCurrentlyCheckedIn($query)
    {
        return $query->whereNull('checked_out_at');
    }

    /**
     * Scope to checked in today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('checked_in_at', today());
    }
}
