<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount LineItem for free_hours wallet credits.
 *
 * Category product (no backing model). Emitted by Finance::price() when
 * a user's free_hours balance covers part or all of a rehearsal LineItem.
 * The quantity reflects blocks consumed; unit_price is negative cents_per_unit.
 */
class FreeHoursDiscountProduct extends Product
{
    public static string $type = 'free_hours_discount';

    public static ?string $model = null;

    public static function getBillableUnits(?Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(?Model $model = null): int
    {
        return 0;
    }

    public static function getDescription(?Model $model = null): string
    {
        return 'Free hours discount';
    }

    public static function getEligibleWallets(?Model $model = null): array
    {
        return [];
    }

    public static function getUnit(): string
    {
        return 'discount';
    }
}
