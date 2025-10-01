<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credit_type',
        'amount',
        'frequency',
        'source',
        'source_id',
        'starts_at',
        'ends_at',
        'last_allocated_at',
        'next_allocation_at',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_allocated_at' => 'datetime',
        'next_allocation_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
