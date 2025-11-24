<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content_type
 * @property int $points
 * @property int $balance_after
 * @property string $reason
 * @property string $source_type
 * @property int|null $source_id
 * @property int|null $awarded_by_id
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $awardedBy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction awards()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction forContentType(string $contentType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction penalties()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereAwardedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrustTransaction whereUserId($value)
 * @mixin \Eloquent
 */
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
