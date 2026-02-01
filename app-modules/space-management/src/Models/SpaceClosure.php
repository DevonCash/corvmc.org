<?php

namespace CorvMC\SpaceManagement\Models;

use App\Models\User;
use CorvMC\SpaceManagement\Enums\ClosureType;
use CorvMC\Support\Concerns\HasTimePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * Represents a period when the practice space is closed.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property string|null $reason
 * @property ClosureType $type
 * @property string|null $notes
 * @property int $created_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $createdBy
 * @property-read float $duration
 */
class SpaceClosure extends Model
{
    use HasTimePeriod, SoftDeletes;

    protected $fillable = [
        'starts_at',
        'ends_at',
        'reason',
        'type',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'type' => ClosureType::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    protected function getStartTimeField(): string
    {
        return 'starts_at';
    }

    protected function getEndTimeField(): string
    {
        return 'ends_at';
    }

    /**
     * Scope to get active (non-deleted) closures.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType(Builder $query, ClosureType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get closures that overlap with a given period.
     */
    public function scopeOverlapping(Builder $query, \Carbon\Carbon $start, \Carbon\Carbon $end): Builder
    {
        return $query->where('ends_at', '>', $start)
            ->where('starts_at', '<', $end);
    }

    /**
     * Scope to get upcoming closures.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now())
            ->orderBy('starts_at');
    }

    /**
     * Scope to get closures on a specific date.
     */
    public function scopeOnDate(Builder $query, \Carbon\Carbon $date): Builder
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        return $query->where('ends_at', '>', $dayStart)
            ->where('starts_at', '<', $dayEnd);
    }

    /**
     * Check if this closure overlaps with a given period.
     */
    public function overlapsWithPeriod(Period $period): bool
    {
        $closurePeriod = $this->createPeriod();

        if (! $closurePeriod) {
            return false;
        }

        return $closurePeriod->overlapsWith($period);
    }

    /**
     * Get a human-readable time range display.
     */
    public function getTimeRangeAttribute(): string
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return 'TBD';
        }

        if ($this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->format('M j, Y g:i A').' - '.$this->ends_at->format('g:i A');
        }

        return $this->starts_at->format('M j, Y g:i A').' - '.$this->ends_at->format('M j, Y g:i A');
    }
}
