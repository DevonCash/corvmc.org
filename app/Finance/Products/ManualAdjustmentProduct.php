<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Manual adjustment LineItem — staff-entered positive or negative amount.
 *
 * Category product (no backing model). Used for ad-hoc corrections,
 * goodwill credits, or miscellaneous charges that don't fit another product.
 */
class ManualAdjustmentProduct extends Product
{
    public static string $type = 'manual_adjustment';

    public static ?string $model = null;

    public static function getBillableUnits(?Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(?Model $model = null): int
    {
        // Actual amount is set per-LineItem at creation time
        return 0;
    }

    public static function getDescription(?Model $model = null): string
    {
        return 'Manual adjustment';
    }

    public static function getEligibleWallets(?Model $model = null): array
    {
        return [];
    }

    public static function getUnit(): string
    {
        return 'adjustment';
    }
}
