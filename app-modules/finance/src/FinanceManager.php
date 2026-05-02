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

    /**
     * Request-scoped cache for findActiveOrder results.
     * Keyed by "ModelClass:id".
     *
     * @var array<string, Order|null>
     */
    protected array $activeOrderCache = [];

    /**
     * Clear the active order cache for all products referenced by an Order's line items.
     *
     * Called after cancel/refund so the purchasable lock sees the updated state.
     */
    public function clearActiveOrderCache(?Order $order = null): void
    {
        if ($order === null) {
            $this->activeOrderCache = [];

            return;
        }

        foreach ($order->lineItems as $lineItem) {
            if ($lineItem->product_id === null) {
                continue;
            }

            // Find the model class for this product type
            $productType = $lineItem->product_type;
            if (isset($this->productsByType[$productType])) {
                $modelClass = $this->productsByType[$productType]::$model;
                if ($modelClass) {
                    $cacheKey = $modelClass . ':' . $lineItem->product_id;
                    unset($this->activeOrderCache[$cacheKey]);
                }
            }
        }
    }

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
            // If the model uses the Purchasable trait, check its declared
            // lockable fields. Otherwise, only allow status + updated_at.
            $lockableFields = method_exists($model, 'getLockableFields')
                ? $model->getLockableFields()
                : ['status', 'updated_at'];

            $changedKeys = array_keys($model->getDirty());
            $onlyLockableChanged = empty(array_diff($changedKeys, $lockableFields));

            if ($onlyLockableChanged) {
                return;
            }

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

        $cacheKey = get_class($model) . ':' . $model->getKey();

        if (array_key_exists($cacheKey, $this->activeOrderCache)) {
            return $this->activeOrderCache[$cacheKey];
        }

        // Resolve the product type from the registry if not provided
        if ($productType === null) {
            $modelClass = get_class($model);
            if (isset($this->productsByModel[$modelClass])) {
                $productType = $this->productsByModel[$modelClass]::$type;
            } else {
                return $this->activeOrderCache[$cacheKey] = null;
            }
        }

        return $this->activeOrderCache[$cacheKey] = Order::whereHas('lineItems', function ($query) use ($model, $productType) {
            $query->where('product_type', $productType)
                ->where('product_id', $model->getKey());
        })
            ->whereNotState('status', [Cancelled::class, Refunded::class])
            ->with(['lineItems', 'transactions'])
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

    /**
     * Reverse credit deductions for an Order's discount LineItems.
     *
     * For each discount LineItem, adds the consumed blocks back to the
     * user's wallet. Used by Cancelled and Refunded state hooks.
     */
    public function reverseDiscountCredits(Order $order, string $source): void
    {
        if (! $order->user) {
            return;
        }

        foreach ($order->lineItems as $lineItem) {
            if (! $lineItem->isDiscount()) {
                continue;
            }

            $walletKey = str_replace('_discount', '', $lineItem->product_type);
            $blocks = (int) abs((float) $lineItem->quantity);

            if ($blocks > 0) {
                $creditType = CreditType::from($walletKey);
                $order->user->addCredit(
                    amount: $blocks,
                    creditType: $creditType,
                    source: $source,
                    sourceId: $order->id,
                    description: "Reversed: order #{$order->id} {$source}",
                );
            }
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

                    // Convert billable units to credit blocks, then cap at available balance.
                    // e.g. 2 hours at $15/hr with $7.50/block → 2 * (1500/750) = 4 blocks max.
                    $blocksPerBillableUnit = $lineItem->unit_price / $centsPerUnit;
                    $maxBlocksForItem = (float) $lineItem->quantity * $blocksPerBillableUnit;
                    $blocksToApply = min($availableBlocks, $maxBlocksForItem);

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
                    'amount' => $amount, // positive = money received by organization
                    'type' => 'payment',
                    'metadata' => [],
                ]);

                // 8. For Stripe rail: create Checkout Session
                if ($currency === 'stripe' && $order->user) {
                    $this->createStripeCheckout($order, $transaction, "Order #{$order->id}");
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
    // Failure
    // =========================================================================

    /**
     * Fail a Transaction: transition Pending → Failed.
     *
     * Runs inside a DB transaction with a row lock. Idempotent — silently
     * returns the Transaction unchanged if it is already in a terminal state.
     *
     * @param  Transaction  $transaction  A Pending Transaction to fail.
     * @return Transaction  The fresh Transaction.
     *
     * @throws \RuntimeException  If the Transaction is in a non-terminal, non-Pending state that cannot transition to Failed.
     */
    public function fail(Transaction $transaction): Transaction
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($transaction) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);

            // Already terminal — nothing to do
            if ($transaction->isTerminal()) {
                return $transaction;
            }

            if (! ($transaction->status instanceof \CorvMC\Finance\States\TransactionState\Pending)) {
                throw new \RuntimeException(
                    "Cannot fail Transaction [{$transaction->id}]: status is [{$transaction->status->getLabel()}], expected Pending."
                );
            }

            $transaction->status->transitionTo(\CorvMC\Finance\States\TransactionState\Failed::class);
            $transaction->update(['failed_at' => now()]);

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

    // =========================================================================
    // Cancellation
    // =========================================================================

    /**
     * Cancel an Order: transition Pending → Cancelled.
     *
     * The Cancelled state hooks cancel Pending Transactions and reverse
     * credit deductions (service was not delivered). An OrderCancelled
     * event is fired for cross-module cleanup (e.g. ticket cancellation).
     *
     * @param  Order  $order  A Pending Order to cancel.
     * @return Order  The fresh Order.
     *
     * @throws \RuntimeException  If the Order is not in Pending state.
     */
    public function cancel(Order $order): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! ($order->status instanceof \CorvMC\Finance\States\OrderState\Pending)) {
                throw new \RuntimeException(
                    "Cannot cancel Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Pending."
                );
            }

            $order->status->transitionTo(\CorvMC\Finance\States\OrderState\Cancelled::class);

            $this->clearActiveOrderCache($order);

            return $order->fresh(['lineItems', 'transactions']);
        });
    }

    // =========================================================================
    // Refund
    // =========================================================================

    /**
     * Refund an Order: create compensating Transactions, call Stripe, transition to Refunded.
     *
     * Runs inside a DB transaction. For each Cleared payment Transaction,
     * creates a compensating refund Transaction (negative amount):
     *   - Stripe: initiates a Stripe refund via payment_intent_id, refund
     *     Transaction starts Pending (settled by charge.refunded webhook)
     *   - Cash: creates a Pending refund Transaction (settled manually by staff)
     *
     * The Refunded state hooks reverse credit deductions and fire OrderRefunded.
     *
     * @param  Order  $order  A Completed or Comped Order to refund.
     * @return Order  The fresh Order with refund Transactions.
     *
     * @throws \RuntimeException  If the Order is not in Completed or Comped state.
     * @throws \RuntimeException  If a Stripe refund API call fails.
     */
    public function refund(Order $order): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $isCompleted = $order->status instanceof \CorvMC\Finance\States\OrderState\Completed;
            $isComped = $order->status instanceof \CorvMC\Finance\States\OrderState\Comped;

            if (! $isCompleted && ! $isComped) {
                throw new \RuntimeException(
                    "Cannot refund Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Completed or Comped."
                );
            }

            // Create compensating refund Transactions for each Cleared payment
            $clearedPayments = $order->transactions()
                ->where('type', 'payment')
                ->whereState('status', \CorvMC\Finance\States\TransactionState\Cleared::class)
                ->get();

            foreach ($clearedPayments as $payment) {
                $refundAmount = $payment->amount; // payment is positive, refund mirrors as negative

                $refundTransaction = Transaction::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'currency' => $payment->currency,
                    'amount' => -$refundAmount,
                    'type' => 'refund',
                    'metadata' => [
                        'original_transaction_id' => $payment->id,
                    ],
                ]);

                // For Stripe payments: initiate refund via API
                if ($payment->currency === 'stripe') {
                    $paymentIntentId = $payment->metadata['payment_intent_id'] ?? null;

                    if (! $paymentIntentId) {
                        throw new \RuntimeException(
                            "Cannot refund Stripe Transaction [{$payment->id}]: no payment_intent_id in metadata."
                        );
                    }

                    $stripeRefund = \Laravel\Cashier\Cashier::stripe()->refunds->create([
                        'payment_intent' => $paymentIntentId,
                        'amount' => $refundAmount, // Stripe expects positive cents
                    ]);

                    $refundTransaction->update([
                        'metadata' => array_merge($refundTransaction->metadata ?? [], [
                            'stripe_refund_id' => $stripeRefund->id,
                        ]),
                    ]);
                }

                // Cash refund Transactions stay Pending — staff settles manually
            }

            // Transition to Refunded (hooks handle credit reversal + event)
            $order->status->transitionTo(\CorvMC\Finance\States\OrderState\Refunded::class);

            $this->clearActiveOrderCache($order);

            return $order->fresh(['lineItems', 'transactions']);
        });
    }

    // =========================================================================
    // Retry
    // =========================================================================

    /**
     * Create a fresh Stripe Checkout Session for a failed payment on an Order.
     *
     * Finds the first failed/cancelled Stripe Transaction, creates a new
     * Transaction with a fresh Checkout Session, and returns the checkout URL.
     *
     * @return string|null The Checkout URL, or null if no retryable transaction found.
     *
     * @throws \RuntimeException If the Order is not Pending.
     * @throws \Exception If the Stripe API call fails.
     */
    public function retryStripePayment(Order $order): ?string
    {
        if (! ($order->status instanceof \CorvMC\Finance\States\OrderState\Pending)) {
            throw new \RuntimeException(
                "Cannot retry payment on Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Pending."
            );
        }

        $failedTxn = $order->transactions()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', [
                \CorvMC\Finance\States\TransactionState\Failed::class,
                \CorvMC\Finance\States\TransactionState\Cancelled::class,
            ])
            ->first();

        if (! $failedTxn) {
            return null;
        }

        $newTxn = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'currency' => 'stripe',
            'amount' => $failedTxn->amount,
            'type' => 'payment',
            'metadata' => [],
        ]);

        $checkoutUrl = $this->createStripeCheckout($order, $newTxn, "Order #{$order->id} — Retry Payment");

        $newTxn->refresh();
        $newTxn->update([
            'metadata' => array_merge($newTxn->metadata ?? [], [
                'retry_of' => $failedTxn->id,
            ]),
        ]);

        return $checkoutUrl;
    }

    // =========================================================================
    // Payment Method Switch
    // =========================================================================

    /**
     * Switch an Order's pending cash payment to a Stripe checkout.
     *
     * Cancels the pending cash Transaction, creates a new Stripe Transaction
     * with a Checkout Session, and returns the checkout URL.
     *
     * @return string|null The Checkout URL, or null if no switchable transaction found.
     *
     * @throws \RuntimeException If the Order is not Pending.
     */
    public function switchToStripe(Order $order): ?string
    {
        if (! ($order->status instanceof \CorvMC\Finance\States\OrderState\Pending)) {
            throw new \RuntimeException(
                "Cannot switch payment on Order [{$order->id}]: status is [{$order->status->getLabel()}], expected Pending."
            );
        }

        $cashTxn = $order->transactions()
            ->where('currency', 'cash')
            ->where('type', 'payment')
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
            ->first();

        if (! $cashTxn) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $cashTxn) {
            $amount = $cashTxn->amount;

            // Create the stripe transaction and attempt checkout BEFORE cancelling
            // the cash transaction. If Stripe fails, cash is untouched.
            $newTxn = Transaction::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'currency' => 'stripe',
                'amount' => $amount,
                'type' => 'payment',
                'metadata' => [],
            ]);

            $checkoutUrl = $this->createStripeCheckout($order, $newTxn, "Order #{$order->id}");

            // Stripe succeeded — now safe to cancel the cash transaction
            $cashTxn->status->transitionTo(\CorvMC\Finance\States\TransactionState\Cancelled::class);

            $newTxn->refresh();
            $newTxn->update([
                'metadata' => array_merge($newTxn->metadata ?? [], [
                    'switched_from' => $cashTxn->id,
                ]),
            ]);

            return $checkoutUrl;
        });
    }

    // =========================================================================
    // Sweep & Reconciliation
    // =========================================================================

    /**
     * Sweep stale pending Stripe Transactions by checking their Checkout Session status.
     *
     * For each Pending Stripe payment Transaction older than $hours, retrieves
     * the Checkout Session from Stripe and either settles (paid) or fails (expired/abandoned).
     *
     * @param  int  $hours  Age threshold in hours (default 25, just past Stripe's 24h expiry).
     * @return array{settled: int, failed: int, errors: array<array{transaction_id: int, error: string}>}
     */
    public function sweepStaleTransactions(int $hours = 25): array
    {
        $cutoff = now()->subHours($hours);

        $staleTransactions = Transaction::query()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
            ->where('created_at', '<', $cutoff)
            ->get();

        $settled = 0;
        $failed = 0;
        $errors = [];

        foreach ($staleTransactions as $transaction) {
            $sessionId = $transaction->metadata['session_id'] ?? null;

            if (! $sessionId) {
                $errors[] = [
                    'transaction_id' => $transaction->id,
                    'error' => 'No session_id in metadata',
                ];

                continue;
            }

            try {
                $session = \Laravel\Cashier\Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            } catch (\Exception $e) {
                $errors[] = [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            $paymentStatus = $session->payment_status ?? 'unpaid';
            $sessionStatus = $session->status ?? 'expired';

            if ($paymentStatus === 'paid' && $sessionStatus === 'complete') {
                $paymentIntentId = $session->payment_intent ?? null;

                try {
                    $this->settle($transaction, $paymentIntentId);
                    $settled++;
                } catch (\RuntimeException $e) {
                    // Already settled between our query and now
                    $settled++;
                }
            } else {
                $this->fail($transaction);
                $failed++;
            }
        }

        return compact('settled', 'failed', 'errors');
    }

    /**
     * Reconcile cleared Stripe Transactions against Stripe's payment intents.
     *
     * Compares local amount and status against the Stripe API for each Cleared
     * Stripe payment Transaction within the lookback window. Returns mismatches
     * for staff review. Does NOT make automatic corrections.
     *
     * @param  int  $days  How many days back to check (default 7).
     * @return array{matched: int, mismatches: array<array{transaction_id: int, order_id: int, issue: string}>, errors: int}
     */
    public function reconcileTransactions(int $days = 7): array
    {
        $since = now()->subDays($days);

        $transactions = Transaction::query()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', \CorvMC\Finance\States\TransactionState\Cleared::class)
            ->where('cleared_at', '>=', $since)
            ->whereNotNull('cleared_at')
            ->get();

        $matched = 0;
        $mismatches = [];
        $errors = 0;

        foreach ($transactions as $transaction) {
            $paymentIntentId = $transaction->metadata['payment_intent_id'] ?? null;

            if (! $paymentIntentId) {
                $mismatches[] = [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'issue' => 'No payment_intent_id in metadata',
                ];

                continue;
            }

            try {
                $paymentIntent = \Laravel\Cashier\Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
            } catch (\Exception $e) {
                $errors++;

                continue;
            }

            $stripeAmount = $paymentIntent->amount_received ?? 0;
            $stripeStatus = $paymentIntent->status ?? 'unknown';

            if ((int) $stripeAmount !== (int) $transaction->amount) {
                $mismatches[] = [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'issue' => "Amount mismatch: local={$transaction->amount}, stripe={$stripeAmount}",
                ];

                continue;
            }

            if ($stripeStatus !== 'succeeded') {
                $mismatches[] = [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'issue' => "Stripe status is '{$stripeStatus}', expected 'succeeded'",
                ];

                continue;
            }

            $matched++;
        }

        return compact('matched', 'mismatches', 'errors');
    }

    /**
     * Archive old Stripe webhook events to a JSONL file and delete them.
     *
     * Streams events older than $days to a timestamped JSONL file in
     * storage/app/finance/archives/, then deletes only the archived rows.
     *
     * @param  int  $days  Age threshold in days (default 90).
     * @return array{archived: int, file: string|null}
     */
    public function archiveWebhookEvents(int $days = 90): array
    {
        $cutoff = now()->subDays($days);

        $count = \Illuminate\Support\Facades\DB::table('stripe_webhook_events')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            return ['archived' => 0, 'file' => null];
        }

        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('finance/archives');

        $filename = 'finance/archives/webhook-events-' . now()->format('Y-m-d-His') . '.jsonl';
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($filename);

        $written = 0;
        $maxArchivedId = 0;
        $handle = fopen($path, 'w');

        try {
            \Illuminate\Support\Facades\DB::table('stripe_webhook_events')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->chunk(500, function ($events) use ($handle, &$written, &$maxArchivedId) {
                    foreach ($events as $event) {
                        fwrite($handle, json_encode((array) $event) . "\n");
                        $maxArchivedId = max($maxArchivedId, $event->id);
                        $written++;
                    }
                });
        } finally {
            fclose($handle);
        }

        if ($written === 0 || $maxArchivedId === 0) {
            return ['archived' => 0, 'file' => null];
        }

        \Illuminate\Support\Facades\DB::table('stripe_webhook_events')
            ->where('id', '<=', $maxArchivedId)
            ->delete();

        return ['archived' => $written, 'file' => $filename];
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    /**
     * Create a Stripe Checkout Session for a Transaction and persist session metadata.
     *
     * @param  Order  $order  The parent Order.
     * @param  Transaction  $transaction  The Stripe Transaction to attach the session to.
     * @param  string  $description  The product name shown in Stripe checkout.
     * @return string  The Checkout URL.
     */
    private function createStripeCheckout(Order $order, Transaction $transaction, string $description): string
    {
        $user = $order->user;

        $checkout = $user->checkoutCharge(
            $transaction->amount,
            $description,
            1,
            [
                'metadata' => [
                    'type' => 'order',
                    'transaction_id' => $transaction->id,
                    'order_id' => $order->id,
                ],
                'success_url' => route('checkout.success') . '?user_id=' . $user->id . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel') . '?user_id=' . $user->id . '&type=order&transaction_id=' . $transaction->id,
            ]
        );

        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'session_id' => $checkout->id,
                'checkout_url' => $checkout->url,
            ]),
        ]);

        return $checkout->url;
    }
}
