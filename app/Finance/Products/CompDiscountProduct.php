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

    public static function getBillableUnits(Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(Model $model = null): int
    {
        // Actual discount amount is set per-Order at commit time
        return 0;
    }

    public static function getDescription(Model $model = null): string
    {
        return 'Complimentary — no charge';
    }

    public static function getEligibleWallets(Model $model = null): array
    {
        return [];
    }

    public static function getUnit(): string
    {
        return 'discount';
    }
}
