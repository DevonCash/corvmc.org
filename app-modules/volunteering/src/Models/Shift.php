<?php

namespace CorvMC\Volunteering\Models;

use CorvMC\Volunteering\Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Tags\HasTags;

/**
 * Shift — a specific volunteer need tied to an event and position.
 *
 * "Friday's show needs 1 Sound Person." One Shift row per position per event.
 * The event relationship is resolved in the integration layer via
 * resolveRelationUsing to keep this module decoupled from Events.
 *
 * @property int $id
 * @property int $position_id
 * @property int|null $event_id
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon $end_at
 * @property int $capacity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Position $position
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HourLog> $hourLogs
 */
class Shift extends Model
{
    use HasFactory, HasTags;

    protected $table = 'volunteer_shifts';

    protected $fillable = [
        'position_id',
        'event_id',
        'start_at',
        'end_at',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    protected static function newFactory(): ShiftFactory
    {
        return ShiftFactory::new();
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Note: event() relationship is resolved via resolveRelationUsing
     * in the integration layer (AppServiceProvider), not here.
     */

    public function hourLogs(): HasMany
    {
        return $this->hasMany(HourLog::class, 'shift_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Shifts for a specific event.
     */
    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Shifts that haven't started yet.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>', now());
    }

    /**
     * Shifts that still have capacity for more volunteers.
     */
    public function scopeWithAvailableCapacity(Builder $query): Builder
    {
        return $query->where('capacity', '>', function ($sub) {
            $sub->selectRaw('count(*)')
                ->from('volunteer_hour_logs')
                ->whereColumn('volunteer_hour_logs.shift_id', 'volunteer_shifts.id')
                ->whereNotIn('volunteer_hour_logs.status', HourLog::TERMINAL_STATUSES);
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Count of active (non-terminal) hour logs for this shift.
     */
    public function filledSlots(): int
    {
        return $this->hourLogs()
            ->whereNotIn('status', HourLog::TERMINAL_STATUSES)
            ->count();
    }

    /**
     * Whether this shift has room for more volunteers.
     */
    public function hasCapacity(): bool
    {
        return $this->filledSlots() < $this->capacity;
    }
}
