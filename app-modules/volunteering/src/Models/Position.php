<?php

namespace CorvMC\Volunteering\Models;

use CorvMC\Volunteering\Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

/**
 * Position — a reusable definition of a volunteer job.
 *
 * "Sound Person", "Door Volunteer", "Host", "Grant Writer", etc.
 * Positions describe what the job is, what skills are helpful, and
 * what the responsibilities look like. They're created by staff and
 * reused across events and self-reported work.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Shift> $shifts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HourLog> $hourLogs
 */
class Position extends Model
{
    use HasFactory, HasTags, SoftDeletes;

    protected $table = 'volunteer_positions';

    protected $fillable = [
        'title',
        'description',
    ];

    protected static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'position_id');
    }

    /**
     * Hour logs submitted directly against this position (self-reported work).
     */
    public function hourLogs(): HasMany
    {
        return $this->hasMany(HourLog::class, 'position_id');
    }
}
