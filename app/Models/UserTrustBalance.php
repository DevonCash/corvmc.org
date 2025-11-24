<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content_type
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance aboveThreshold(int $threshold)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance between(int $min, int $max)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance forContentType(string $contentType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTrustBalance whereUserId($value)
 * @mixin \Eloquent
 */
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
