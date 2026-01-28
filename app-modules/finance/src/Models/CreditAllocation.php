<?php

namespace CorvMC\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $credit_type
 * @property int $amount
 * @property string $frequency
 * @property string $source
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $last_allocated_at
 * @property \Illuminate\Support\Carbon|null $next_allocation_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereLastAllocatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereNextAllocationAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditAllocation whereUserId($value)
 *
 * @mixin \Eloquent
 */
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
