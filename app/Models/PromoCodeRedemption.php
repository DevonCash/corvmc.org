<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promo_code_id
 * @property int $user_id
 * @property int $credit_transaction_id
 * @property \Illuminate\Support\Carbon $redeemed_at
 * @property-read \App\Models\CreditTransaction $creditTransaction
 * @property-read \App\Models\PromoCode $promoCode
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption whereCreditTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption wherePromoCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption whereRedeemedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCodeRedemption whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PromoCodeRedemption extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'credit_transaction_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }
}
