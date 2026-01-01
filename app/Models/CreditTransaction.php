<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CreditTransaction - Immutable Audit Log (Double-Entry Bookkeeping Pattern)
 *
 * Records EVERY credit change as a new row. Append-only, never updated.
 *
 * This is the AUDIT TRAIL for "show me the transaction history".
 *
 * Works with UserCredit (current state table):
 * - amount: The delta (+16 for add, -4 for deduct)
 * - balance_after: Snapshot of UserCredit.balance after this transaction
 * - source: What caused this change ('monthly_reset', 'reservation_usage', 'promo_code')
 * - source_id: Link to the entity (e.g., reservation_id, promo_code_id)
 *
 * Example transaction flow:
 * 1. Lock UserCredit row (for update)
 * 2. Update UserCredit.balance += amount
 * 3. Create CreditTransaction(amount, balance_after: UserCredit.balance)
 * 4. Commit transaction
 *
 * Use cases:
 * - Display transaction history to users
 * - Calculate "used this month" by summing negative amounts
 * - Audit/verify balance calculations
 * - Refund credits (create positive transaction)
 *
 * DO NOT query this table for current balance - use UserCredit::getBalance() instead.
 *
 * @property int $id
 * @property int $user_id
 * @property string $credit_type
 * @property int $amount
 * @property int $balance_after
 * @property string $source
 * @property int|null $source_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
class CreditTransaction extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at

    protected $fillable = [
        'user_id',
        'credit_type',
        'amount',
        'balance_after',
        'source',
        'source_id',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
