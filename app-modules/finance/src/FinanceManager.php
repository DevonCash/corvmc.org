<?php

namespace CorvMC\Finance;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Exceptions\PurchasableLockedException;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Order;
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
    // Commitment, Settlement, Refund — stubs for later epics
    // =========================================================================

    // Finance::commit() — Epic 5
    // Finance::settle() — Epic 6
    // Finance::refund() — Epic 7
}
