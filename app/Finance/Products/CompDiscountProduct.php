<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Comp (complimentary) discount — zeroes out the Order total.
 *
 * Category product (no backing model). Applied when staff comps an Order.
 * The discount amount matches the Order subtotal so total goes to zero.
 */
class CompDiscountProduct extends Product
{
    public static string $type = 'comp_discount';

    public static ?string $model = null;

    public static function billableUnits(Model $model = null): float
    {
        return 1;
    }

    public static function pricePerUnit(Model $model = null): int
    {
        // Actual discount amount is set per-Order at commit time
        return 0;
    }

    public static function description(Model $model = null): string
    {
        return 'Complimentary — no charge';
    }

    public static function eligibleWallets(Model $model = null): array
    {
        return [];
    }

    public static function unit(): string
    {
        return 'discount';
    }
}
