<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrustBalance extends Model
{
    protected $fillable = [
        'user_id',
        'content_type',
        'balance',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    /**
     * Get the user that owns the trust balance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get balances for a specific content type.
     */
    public function scopeForContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to get balances above a threshold.
     */
    public function scopeAboveThreshold($query, int $threshold)
    {
        return $query->where('balance', '>=', $threshold);
    }

    /**
     * Scope to get balances within a range.
     */
    public function scopeBetween($query, int $min, int $max)
    {
        return $query->where('balance', '>=', $min)
                    ->where('balance', '<=', $max);
    }
}
