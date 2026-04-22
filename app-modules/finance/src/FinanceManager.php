<?php

namespace CorvMC\Finance;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Products\Product;
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
            $this->productsByType[$type] = $productClass;

            if ($productClass::$model !== null) {
                $this->productsByModel[$productClass::$model] = $productClass;
            }
        }
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
    // Pricing, Commitment, Settlement, Refund — stubs for later epics
    // =========================================================================

    // Finance::price() — Epic 4
    // Finance::commit() — Epic 5
    // Finance::settle() — Epic 6
    // Finance::refund() — Epic 7
}
