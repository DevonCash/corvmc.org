<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustTransaction extends Model
{
    const UPDATED_AT = null; // Immutable records, no updated_at

    protected $fillable = [
        'user_id',
        'content_type',
        'points',
        'balance_after',
        'reason',
        'source_type',
        'source_id',
        'awarded_by_id',
        'metadata',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who awarded/penalized points.
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_id');
    }

    /**
     * Scope to get transactions for a specific content type.
     */
    public function scopeForContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to get only awards (positive points).
     */
    public function scopeAwards($query)
    {
        return $query->where('points', '>', 0);
    }

    /**
     * Scope to get only penalties (negative points).
     */
    public function scopePenalties($query)
    {
        return $query->where('points', '<', 0);
    }
}
