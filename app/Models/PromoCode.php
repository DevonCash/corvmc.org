<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
