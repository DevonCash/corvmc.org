<?php

namespace CorvMC\Finance\Models;

use App\Models\User;
use CorvMC\Finance\States\TransactionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

/**
 * Transaction — an append-only record of legal currency movement.
 *
 * Only Stripe and cash. Not credits, not discounts, not allocations.
 * Those live elsewhere (credit ledger for balance movements, LineItems for discounts).
 *
 * Sign convention (from the organization's perspective):
 *   payment → positive (money received)
 *   refund  → negative (money returned)
 *   fee     → negative (processing cost)
 *
 * Row-level immutability: amount, currency, type, order_id, and user_id are
 * frozen once written. Only status, terminal timestamps, and metadata mutate.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $user_id
 * @property string $currency 'stripe' or 'cash'
 * @property int $amount Cents — positive for payment, negative for refund/fee
 * @property string $type 'payment', 'refund', or 'fee'
 * @property TransactionState $status
 * @property \Illuminate\Support\Carbon|null $cleared_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Order $order
 * @property-read User|null $user
 */
class Transaction extends Model
{
    use HasFactory, HasStates;

    /**
     * Attributes that are frozen after first write.
     */
    private const IMMUTABLE_ATTRIBUTES = [
        'amount',
        'currency',
        'type',
        'order_id',
        'user_id',
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'currency',
        'amount',
        'type',
        'status',
        'cleared_at',
        'cancelled_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionState::class,
            'amount' => 'integer',
            'cleared_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // Immutability
    // =========================================================================

    protected static function booted(): void
    {
        static::updating(function (Transaction $transaction) {
            foreach (self::IMMUTABLE_ATTRIBUTES as $attr) {
                if ($transaction->isDirty($attr)) {
                    throw new \RuntimeException(
                        "Transaction.{$attr} is immutable once written."
                    );
                }
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function isFee(): bool
    {
        return $this->type === 'fee';
    }

    public function isTerminal(): bool
    {
        return $this->status->isFinal();
    }
}
