<?php

namespace CorvMC\Moderation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content_type
 * @property string $level
 * @property \Illuminate\Support\Carbon $achieved_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement forContentType(string $contentType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement forLevel(string $level)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement whereAchievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustAchievement whereUserId($value)
 *
 * @mixin \Eloquent
 */
class TrustAchievement extends Model
{
    const UPDATED_AT = null; // Immutable records, no updated_at

    protected $fillable = [
        'user_id',
        'content_type',
        'level',
        'achieved_at',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
    ];

    /**
     * Get the user that owns the achievement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get achievements for a specific content type.
     */
    public function scopeForContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to get achievements for a specific level.
     */
    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}
