<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $credit_type
 * @property int $credit_amount
 * @property int|null $max_uses
 * @property int $uses_count
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PromoCodeRedemption> $redemptions
 * @property-read int|null $redemptions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCreditAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereMaxUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereUsesCount($value)
 * @mixin \Eloquent
 */
class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'credit_type',
        'credit_amount',
        'max_uses',
        'uses_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'credit_amount' => 'integer',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }
}
