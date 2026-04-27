<?php

namespace CorvMC\Volunteering\Models;

use App\Models\User;
use CorvMC\Volunteering\Database\Factories\HourLogFactory;
use CorvMC\Volunteering\States\HourLogState;
use CorvMC\Volunteering\States\HourLogState\Approved;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\ModelStates\HasStates;
use Spatie\Tags\HasTags;

/**
 * HourLog — the universal record of volunteer work.
 *
 * Every piece of volunteering — a shift worked, a self-reported block of
 * grant writing — is one HourLog row. Two creation paths, one model:
 * shift-based (has shift_id) and self-reported (has position_id).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $shift_id
 * @property int|null $position_id
 * @property HourLogState $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int|null $reviewed_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $minutes
 * @property-read User $user
 * @property-read Shift|null $shift
 * @property-read Position|null $position
 * @property-read User|null $reviewer
 */
class HourLog extends Model
{
    use HasFactory, HasStates, HasTags;

    /**
     * Statuses that represent a concluded hour log (no longer active).
     * Used for capacity checks, partial unique indexes, and scope filters.
     */
    public const TERMINAL_STATUSES = ['released', 'checked_out', 'rejected'];

    /**
     * Statuses that count toward volunteer hour reporting.
     */
    public const COUNTABLE_STATUSES = ['checked_out', 'approved'];

    protected $table = 'volunteer_hour_logs';

    protected $fillable = [
        'user_id',
        'shift_id',
        'position_id',
        'status',
        'started_at',
        'ended_at',
        'reviewed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => HourLogState::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HourLog $hourLog) {
            $hasShift = $hourLog->shift_id !== null;
            $hasPosition = $hourLog->position_id !== null;

            if ($hasShift === $hasPosition) {
                throw new InvalidArgumentException(
                    'HourLog must have exactly one of shift_id or position_id set.'
                );
            }
        });
    }

    protected static function newFactory(): HourLogFactory
    {
        return HourLogFactory::new();
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * Computed minutes from started_at to ended_at.
     * Not a stored column — avoids PostgreSQL/SQLite dialect differences.
     */
    public function getMinutesAttribute(): ?int
    {
        if ($this->started_at === null || $this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Direct position — only set for self-reported work.
     * For shift-based work, the position is on the Shift.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolve the position regardless of whether this is shift-based or self-reported.
     */
    public function resolvePosition(): ?Position
    {
        return $this->position ?? $this->shift?->position;
    }

    /**
     * Whether this hour log counts toward reporting totals.
     */
    public function countsTowardReporting(): bool
    {
        return $this->status instanceof CheckedOut
            || $this->status instanceof Approved;
    }

    /**
     * Whether this is shift-based (vs self-reported).
     */
    public function isShiftBased(): bool
    {
        return $this->shift_id !== null;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Only hour logs that count toward reporting.
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereIn('status', self::COUNTABLE_STATUSES);
    }

    /**
     * Hour logs for a specific shift.
     */
    public function scopeForShift(Builder $query, int $shiftId): Builder
    {
        return $query->where('shift_id', $shiftId);
    }

    /**
     * Active (non-terminal) hour logs — used for capacity checks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }
}
