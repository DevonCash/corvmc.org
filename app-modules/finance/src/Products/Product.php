<?php

namespace CorvMC\Finance\Products;

use Illuminate\Database\Eloquent\Model;

/**
 * Product — Finance's description of how to price and ledger a given thing.
 *
 * Each Product subclass declares a $type string and optionally a $model class.
 * Model-backed Products (rehearsal, ticket, equipment loan) bind to a domain
 * model instance and proxy static methods through __get. Category Products
 * (processing fee, comp discount) have no backing model.
 *
 * Products replace the old Chargeable interface. Domain models no longer
 * import Finance types — Finance describes them from its own side.
 */
abstract class Product
{
    /**
     * Unique string key for this product type.
     * Used in LineItem.product_type and the Finance registry.
     */
    public static string $type;

    /**
     * The domain model class this product describes, or null for category products.
     */
    public static ?string $model = null;

    /**
     * The bound domain model instance (for model-backed products).
     */
    protected ?Model $instance = null;

    public function __construct(?Model $instance = null)
    {
        $this->instance = $instance;
    }

    /**
     * Bind a domain model instance and return a new Product wrapper.
     */
    public static function for(?Model $instance = null): static
    {
        return new static($instance);
    }

    /**
     * Proxy property access to getter methods, passing the bound instance.
     *
     * Allows: $product->billableUnits instead of ProductClass::getBillableUnits($model)
     */
    public function __get(string $name): mixed
    {
        $method = 'get' . ucfirst($name);

        if (method_exists(static::class, $method)) {
            return $this->instance !== null
                ? static::$method($this->instance)
                : static::$method();
        }

        throw new \RuntimeException("Property [{$name}] does not exist on " . static::class);
    }

    // =========================================================================
    // Abstract methods — every Product must define these
    // =========================================================================

    /**
     * Number of billable units for the given model.
     * For category products (no model), return 1.
     */
    abstract public static function getBillableUnits(?Model $model = null): float;

    /**
     * Price per unit in cents.
     */
    abstract public static function getPricePerUnit(?Model $model = null): int;

    /**
     * Human-readable description for receipts and invoices.
     */
    abstract public static function getDescription(?Model $model = null): string;

    /**
     * Wallet type keys that can discount a LineItem for this product.
     * Returns an empty array if no wallets apply.
     */
    abstract public static function getEligibleWallets(?Model $model = null): array;

    // =========================================================================
    // Derived helpers
    // =========================================================================

    /**
     * The unit label for LineItem.unit ('hour', 'ticket', 'fee', etc.)
     */
    abstract public static function getUnit(): string;

    /**
     * Compute the total amount in cents: billableUnits × pricePerUnit.
     */
    public function totalAmount(): int
    {
        return (int) round($this->billableUnits * $this->pricePerUnit);
    }

    /**
     * Get the product type string.
     */
    public function getType(): string
    {
        return static::$type;
    }

    /**
     * Get the bound model instance (if any).
     */
    public function getModel(): ?Model
    {
        return $this->instance;
    }
}
