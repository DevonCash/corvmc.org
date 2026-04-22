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

    /**
     * HasTimePeriod configuration
     */
    protected static string $startTimeField = 'starts_at';
    protected static string $endTimeField = 'ends_at';

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
     * Scope to get upcoming closures.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->notEnded()->orderByStart();
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

}
