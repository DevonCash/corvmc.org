<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
