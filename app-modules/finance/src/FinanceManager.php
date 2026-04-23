<?php

namespace CorvMC\Finance;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Exceptions\PurchasableLockedException;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\Products\Product;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Refunded;
use Illuminate\Database\Eloquent\Model;

/**
 * FinanceManager — the singleton backing the Finance facade.
 *
 * Owns the Product registry and provides the public API for pricing,
 * commitment, settlement, refund, and balance operations.
 */
class FinanceManager
{
    /**
     * Registered product classes, keyed by type string.
     *
     * @var array<string, class-string<Product>>
     */
    protected array $productsByType = [];

    /**
     * Registered product classes, keyed by model class.
     *
     * @var array<class-string<Model>, class-string<Product>>
     */
    protected array $productsByModel = [];

    // =========================================================================
    // Registry
    // =========================================================================

    /**
     * Register an array of Product classes.
     *
     * @param array<class-string<Product>> $productClasses
     */
    public function register(array $productClasses): void
    {
        foreach ($productClasses as $productClass) {
            $type = $productClass::$type;

            if (isset($this->productsByType[$type])) {
                throw new \RuntimeException(
                    "Duplicate product type [{$type}]: {$productClass} conflicts with {$this->productsByType[$type]}."
                );
            }

            $this->productsByType[$type] = $productClass;

            if ($productClass::$model !== null) {
                $this->productsByModel[$productClass::$model] = $productClass;
                $this->attachPurchasableLock($productClass::$model, $type);
            }
        }
    }

    /**
     * Attach an updating observer to a model class that prevents modification
     * while an active (non-terminal) Order references it.
     */
    protected function attachPurchasableLock(string $modelClass, string $productType): void
    {
        $modelClass::updating(function (Model $model) use ($productType) {
            $activeOrder = $this->findActiveOrder($model, $productType);

            if ($activeOrder) {
                throw new PurchasableLockedException($model, $activeOrder);
            }
        });
    }

    /**
     * Find an active (non-terminal) Order that references this model instance
     * via a LineItem.
     */
    public function findActiveOrder(Model $model, ?string $productType = null): ?Order
    {
        if (! $model->exists) {
            return null;
        }

        // Resolve the product type from the registry if not provided
        if ($productType === null) {
            $modelClass = get_class($model);
            if (isset($this->productsByModel[$modelClass])) {
                $productType = $this->productsByModel[$modelClass]::$type;
            } else {
                return null;
            }
        }

        return Order::whereHas('lineItems', function ($query) use ($model, $productType) {
            $query->where('product_type', $productType)
                ->where('product_id', $model->getKey());
        })
            ->whereNotState('status', [Cancelled::class, Refunded::class])
            ->first();
    }

    /**
     * Resolve the Product for a given domain model instance.
     *
     * Walks the class hierarchy so subclasses are matched to parent registrations.
     */
    public function productFor(Model $model): Product
    {
        $class = get_class($model);

        // Direct match
        if (isset($this->productsByModel[$class])) {
            $productClass = $this->productsByModel[$class];
            return $productClass::for($model);
        }

        // Walk parent classes (e.g. RehearsalReservation extends Reservation)
        foreach (class_parents($class) as $parent) {
            if (isset($this->productsByModel[$parent])) {
                $productClass = $this->productsByModel[$parent];
                return $productClass::for($model);
            }
        }

        throw new \RuntimeException(
            "No Product registered for model class [{$class}]."
        );
    }

    /**
     * Resolve a Product by its type string (for category products without a model).
     */
    public function productByType(string $type): Product
    {
        if (! isset($this->productsByType[$type])) {
            throw new \RuntimeException(
                "No Product registered for type [{$type}]."
            );
        }

        $productClass = $this->productsByType[$type];
        return $productClass::for();
    }

    /**
     * All registered product type strings.
     *
     * @return string[]
     */
    public function registeredTypes(): array
    {
        return array_keys($this->productsByType);
    }

    /**
     * Check if a product type is registered.
     */
    public function isRegisteredType(string $type): bool
    {
        return isset($this->productsByType[$type]);
    }

    // =========================================================================
    // Balance — thin wrappers over existing CreditService / HasCredits
    // =========================================================================

    /**
     * Get the user's current balance for a wallet type, in blocks.
     */
    public function balance(User $user, string $walletType): int
    {
        $creditType = CreditType::from($walletType);

        return $user->getCreditBalance($creditType);
    }

    /**
     * Allocate credits to a user's wallet.
     */
    public function allocate(
        User $user,
        string $walletType,
        int $amount,
        string $reason,
        ?Model $source = null
    ): void {
        $creditType = CreditType::from($walletType);

        $user->addCredit(
            amount: $amount,
            creditType: $creditType,
            source: $reason,
            sourceId: $source?->getKey(),
            description: $reason,
        );
    }

    /**
     * Adjust a user's wallet balance (positive to add, negative to deduct).
     */
    public function adjust(User $user, string $walletType, int $amount, string $reason): void
    {
        $creditType = CreditType::from($walletType);

        if ($amount >= 0) {
            $user->addCredit(
                amount: $amount,
                creditType: $creditType,
                source: 'adjustment',
                description: $reason,
            );
        } else {
            $user->deductCredit(
                amount: abs($amount),
                creditType: $creditType,
                source: 'adjustment',
                sourceId: null,
            );
        }
    }

    // =========================================================================
    // Pricing
    // =========================================================================

    /**
     * Build unpersisted LineItems for the given domain models.
     *
     * For each model, resolves its Product and creates a base LineItem.
     * When a User is provided, walks each Product's eligible wallets and
     * emits discount LineItems (negative amounts) up to the available
     * balance, first-come-first-served across items.
     *
     * Pure — no DB writes, no wallet mutations.
     *
     * @param  array<\Illuminate\Database\Eloquent\Model>  $models
     * @return \Illuminate\Support\Collection<int, LineItem>
     */
    public function price(array $models, ?User $user = null): \Illuminate\Support\Collection
    {
        $lineItems = collect();

        // Track remaining wallet balances (in blocks) across all items
        $remainingBalances = [];

        // Build base LineItems
        $baseItems = [];
        foreach ($models as $model) {
            $product = $this->productFor($model);

            $lineItem = new LineItem([
                'product_type' => $product->getType(),
                'product_id' => $model->getKey(),
                'description' => $product->description,
                'unit' => $product->unit,
                'unit_price' => $product->pricePerUnit,
                'quantity' => $product->billableUnits,
                'amount' => $product->totalAmount(),
            ]);

            $lineItems->push($lineItem);
            $baseItems[] = ['lineItem' => $lineItem, 'product' => $product];
        }

        // Apply wallet discounts if a user is provided
        if ($user !== null) {
            foreach ($baseItems as $entry) {
                $lineItem = $entry['lineItem'];
                $product = $entry['product'];
                $eligibleWallets = $product->eligibleWallets;

                foreach ($eligibleWallets as $walletKey) {
                    // Verify the derived discount product type is registered
                    $discountType = $walletKey . '_discount';

                    if (! $this->isRegisteredType($discountType)) {
                        throw new \RuntimeException(
                            "Wallet [{$walletKey}] requires a registered discount product type [{$discountType}]. "
                                . 'Create and register the Product class before using this wallet.'
                        );
                    }

                    // Lazy-load wallet balance on first encounter
                    if (! array_key_exists($walletKey, $remainingBalances)) {
                        $remainingBalances[$walletKey] = $this->balance($user, $walletKey);
                    }

                    $availableBlocks = $remainingBalances[$walletKey];

                    if ($availableBlocks <= 0) {
                        continue;
                    }

                    $centsPerUnit = (int) config("finance.wallets.{$walletKey}.cents_per_unit", 0);

                    if ($centsPerUnit <= 0) {
                        continue;
                    }

                    // How many blocks can we apply? Capped by billable units on this item.
                    $blocksToApply = min($availableBlocks, (float) $lineItem->quantity);

                    // Convert to cents and cap at the base LineItem amount
                    $discountCents = (int) ($blocksToApply * $centsPerUnit);
                    $discountCents = min($discountCents, $lineItem->amount);

                    if ($discountCents <= 0) {
                        continue;
                    }

                    // If the amount cap reduced the discount, floor to whole blocks
                    // so we never consume a fractional credit block.
                    $actualBlocks = (int) floor($discountCents / $centsPerUnit);

                    if ($actualBlocks <= 0) {
                        continue;
                    }

                    // Recompute discount from whole blocks
                    $discountCents = $actualBlocks * $centsPerUnit;

                    $walletLabel = config("finance.wallets.{$walletKey}.label", $walletKey);

                    $discountItem = new LineItem([
                        'product_type' => $discountType,
                        'product_id' => null,
                        'description' => "{$walletLabel} applied",
                        'unit' => 'discount',
                        'unit_price' => -$centsPerUnit,
                        'quantity' => $actualBlocks,
                        'amount' => -$discountCents,
                    ]);

                    $lineItems->push($discountItem);
                    $remainingBalances[$walletKey] -= $actualBlocks;
                }
            }
        }

        return $lineItems;
    }

    // =========================================================================
    // Commitment
    // =========================================================================

    /**
     * Commit an Order: reprice, deduct credits, create Transactions, open Stripe session.
     *
     * Runs inside a DB transaction. On success the Order has persisted LineItems,
     * Transactions for each rail, and credit deductions are applied. If a Stripe
     * rail is present, Transaction.metadata contains the checkout_url for redirect.
     *
     * If the Order is fully covered by discounts (no Transactions needed),
     * it transitions directly to Completed.
     *
     * @param  Order  $order  A Pending Order with at least one LineItem already attached.
     * @param  array<string, int>  $rails  Payment rails and amounts, e.g. ['stripe' => 2500, 'cash' => 500].
     * @param  bool  $coversFees  Whether to add a processing fee LineItem for Stripe payments.
     * @return Order  The fresh Order with all relationships loaded.
     *
     * @throws \RuntimeException  If the Order is not in Pending state.
     * @throws \RuntimeException  If rail amounts don't cover the net total.
     */
    public function commit(Order $order, array $rails = [], bool $coversFees = false): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $rails, $coversFees) {
            // 1. Lock the Order row
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! ($order->status instanceof \CorvMC\Finance\States\OrderState\Pending)) {
                throw new \RuntimeException(
                    "Cannot commit Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Pending."
                );
            }

            // 2. Resolve domain models from existing LineItems
            $models = $order->resolveProducts();

            // 3. Reprice: get fresh LineItems with discounts
            $lineItems = $this->price($models, $order->user);

            // 4. If covering fees and there's a stripe rail, add processing fee LineItem
            if ($coversFees && isset($rails['stripe'])) {
                $subtotal = $lineItems->sum('amount');
                $feeProductClass = get_class($this->productByType('processing_fee'));
                $feeCents = $feeProductClass::computeFee(max(0, (int) $subtotal));

                if ($feeCents > 0) {
                    $feeProduct = $this->productByType('processing_fee');
                    $feeItem = new LineItem([
                        'product_type' => 'processing_fee',
                        'product_id' => null,
                        'description' => $feeProduct->description,
                        'unit' => $feeProduct->unit,
                        'unit_price' => $feeCents,
                        'quantity' => 1,
                        'amount' => $feeCents,
                    ]);
                    $lineItems->push($feeItem);
                }
            }

            // 5. Replace old LineItems with fresh ones
            $order->lineItems()->delete();
            $netTotal = $lineItems->sum('amount');

            // Validate rail amounts cover the net total exactly
            $railTotal = (int) array_sum($rails);

            if ($netTotal > 0 && $railTotal !== (int) $netTotal) {
                throw new \RuntimeException(
                    "Rail amounts ({$railTotal}) do not match net total ({$netTotal}) on Order [{$order->id}]."
                );
            }

            $order->update(['total_amount' => $netTotal]);

            foreach ($lineItems as $lineItem) {
                $lineItem->order_id = $order->id;
                $lineItem->save();
            }

            // 6. Deduct credits for each discount LineItem
            if ($order->user) {
                foreach ($lineItems as $lineItem) {
                    if (! $lineItem->isDiscount()) {
                        continue;
                    }

                    // Derive the wallet key from the product_type (e.g. 'free_hours_discount' → 'free_hours')
                    $walletKey = str_replace('_discount', '', $lineItem->product_type);
                    $blocks = (int) abs((float) $lineItem->quantity);

                    if ($blocks > 0) {
                        $creditType = CreditType::from($walletKey);
                        $order->user->deductCredit(
                            amount: $blocks,
                            creditType: $creditType,
                            source: 'order_commit',
                            sourceId: $order->id,
                        );
                    }
                }
            }

            // 7. Create Transactions for each payment rail
            foreach ($rails as $currency => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                $transaction = Transaction::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'currency' => $currency,
                    'amount' => -$amount, // negative = money leaving customer
                    'type' => 'payment',
                    'metadata' => [],
                ]);

                // 8. For Stripe rail: create Checkout Session
                if ($currency === 'stripe' && $order->user) {
                    $checkout = $order->user->checkoutCharge(
                        $amount,
                        "Order #{$order->id}",
                        1,
                        [
                            'success_url' => route('checkout.success') . '?user_id=' . $order->user_id . '&session_id={CHECKOUT_SESSION_ID}',
                            'cancel_url' => route('checkout.cancel') . '?user_id=' . $order->user_id . '&type=order',
                            'metadata' => [
                                'type' => 'order',
                                'order_id' => $order->id,
                                'transaction_id' => $transaction->id,
                            ],
                        ]
                    );

                    $transaction->update([
                        'metadata' => [
                            'session_id' => $checkout->id,
                            'checkout_url' => $checkout->url,
                        ],
                    ]);
                }
            }

            // 9. If no Transactions (fully covered by discounts), settle immediately
            $hasPendingTransactions = $order->transactions()
                ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
                ->exists();

            if (! $hasPendingTransactions) {
                $order->status->transitionTo(\CorvMC\Finance\States\OrderState\Completed::class);
            }

            return $order->fresh(['lineItems', 'transactions']);
        });
    }

    // =========================================================================
    // Settlement
    // =========================================================================

    /**
     * Settle a Transaction: transition Pending → Cleared.
     *
     * Runs inside a DB transaction. On success the Transaction is Cleared,
     * `cleared_at` is set, and a TransactionCleared event is fired.
     * The listener on that event checks whether the parent Order should
     * transition to Completed.
     *
     * @param  Transaction  $transaction  A Pending Transaction to settle.
     * @param  string|null  $paymentIntentId  Optional Stripe payment intent ID to store in metadata.
     * @return Transaction  The fresh Transaction.
     *
     * @throws \RuntimeException  If the Transaction is not in Pending state.
     */
    public function settle(Transaction $transaction, ?string $paymentIntentId = null): Transaction
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $paymentIntentId) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if (! ($transaction->status instanceof \CorvMC\Finance\States\TransactionState\Pending)) {
                throw new \RuntimeException(
                    "Cannot settle Transaction [{$transaction->id}]: status is [{$transaction->status->getLabel()}], expected Pending."
                );
            }

            // Store payment intent ID if provided (Stripe settlement)
            if ($paymentIntentId !== null) {
                $metadata = $transaction->metadata ?? [];
                $metadata['payment_intent_id'] = $paymentIntentId;
                $transaction->update(['metadata' => $metadata]);
            }

            $transaction->cleared_at = now();
            $transaction->save();
            $transaction->status->transitionTo(\CorvMC\Finance\States\TransactionState\Cleared::class);

            \CorvMC\Finance\Events\TransactionCleared::dispatch($transaction);

            return $transaction->fresh();
        });
    }

    // =========================================================================
    // Comp
    // =========================================================================

    /**
     * Comp an Order: waive payment and transition Pending → Comped.
     *
     * The Comped state hooks cancel any Pending Transactions and fire
     * OrderSettled. Credits already deducted at commit time are NOT
     * reversed — the patron received the service, credits covered
     * their share of the cost.
     *
     * @param  Order  $order  A Pending Order to comp.
     * @return Order  The fresh Order.
     *
     * @throws \RuntimeException  If the Order is not in Pending state.
     */
    public function comp(Order $order): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! ($order->status instanceof \CorvMC\Finance\States\OrderState\Pending)) {
                throw new \RuntimeException(
                    "Cannot comp Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Pending."
                );
            }

            $order->status->transitionTo(\CorvMC\Finance\States\OrderState\Comped::class);

            return $order->fresh(['lineItems', 'transactions']);
        });
    }

    // Finance::refund() — Epic 7
}
