<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
