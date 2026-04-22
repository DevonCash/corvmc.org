<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount LineItem for equipment_credits wallet credits.
 *
 * Category product (no backing model). Emitted by Finance::price() when
 * a user's equipment_credits balance covers part or all of an equipment loan LineItem.
 * The quantity reflects credits consumed; unit_price is negative cents_per_unit.
 */
class EquipmentCreditsDiscountProduct extends Product
{
    public static string $type = 'equipment_credits_discount';

    public static ?string $model = null;

    public static function getBillableUnits(Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(Model $model = null): int
    {
        return 0;
    }

    public static function getDescription(Model $model = null): string
    {
        return 'Equipment credits discount';
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
