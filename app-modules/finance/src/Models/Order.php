<?php

namespace CorvMC\Finance\Models;

use App\Models\User;
use CorvMC\Finance\States\OrderState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * Order — a cohesive purchase commitment.
 *
 * An Order groups LineItems (what was bought) and Transactions (how it was paid).
 * All LineItems on an Order are interdependent — cancellation and refund are
 * Order-level, not per-item.
 *
 * @property int $id
 * @property int|null $user_id
 * @property OrderState $status
 * @property int $total_amount Cents — denormalized sum of LineItems
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LineItem> $lineItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 */
class Order extends Model
{
    use HasFactory, HasStates;

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderState::class,
            'total_amount' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // =========================================================================
    // Query helpers
    // =========================================================================

    /**
     * Whether this Order has reached a settled state (Completed or Comped).
     */
    public function isSettled(): bool
    {
        return $this->status instanceof OrderState\Completed
            || $this->status instanceof OrderState\Comped;
    }

    /**
     * Whether this Order is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Formatted total amount as a dollar string (e.g. "$30.00").
     */
    public function formattedTotal(): string
    {
        return '$' . number_format($this->total_amount / 100, 2);
    }

    /**
     * The sum of Cleared payment Transactions in cents.
     *
     * Payments are positive (money received by the organization),
     * so this is a direct sum.
     */
    public function paidAmount(): int
    {
        return (int) $this->transactions()
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Cleared::class)
            ->where('type', 'payment')
            ->sum('amount');
    }

    /**
     * Outstanding balance: total_amount minus paid amount.
     * Terminal states (comped, refunded, cancelled) have no outstanding balance.
     */
    public function outstandingAmount(): int
    {
        if ($this->isSettled() || $this->status instanceof OrderState\Cancelled || $this->status instanceof OrderState\Refunded) {
            return 0;
        }

        return max(0, $this->total_amount - $this->paidAmount());
    }

    /**
     * Get the Stripe Checkout Session URL for this Order, if one exists.
     *
     * Looks for a Pending Stripe payment Transaction and returns the
     * session URL from its metadata. Returns null if no pending Stripe
     * Transaction exists or if the URL is not set.
     */
    public function checkoutUrl(): ?string
    {
        $transaction = $this->transactions()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
            ->first();

        return $transaction?->metadata['checkout_url'] ?? null;
    }

    /**
     * Resolve domain model instances from this Order's base (non-discount) LineItems.
     *
     * Returns an array of Eloquent models keyed the same as the LineItems.
     * Skips LineItems that have no product_id (category products like fees/discounts).
     *
     * @return array<\Illuminate\Database\Eloquent\Model>
     */
    public function resolveProducts(): array
    {
        $models = [];

        foreach ($this->lineItems as $lineItem) {
            if ($lineItem->product_id === null || $lineItem->isDiscount()) {
                continue;
            }

            $product = \CorvMC\Finance\Facades\Finance::productByType($lineItem->product_type);
            $modelClass = $product::$model;

            if ($modelClass === null) {
                continue;
            }

            $model = $modelClass::find($lineItem->product_id);

            if ($model !== null) {
                $models[] = $model;
            }
        }

        return $models;
    }
}
