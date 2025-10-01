<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credit_type',
        'balance',
        'max_balance',
        'rollover_enabled',
        'expires_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'max_balance' => 'integer',
        'rollover_enabled' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
