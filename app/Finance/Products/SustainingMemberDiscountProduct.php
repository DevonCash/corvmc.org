<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount LineItem for sustaining-member benefits.
 *
 * Category product (no backing model). The discount amount is computed
 * at commit time based on the member's benefit level. pricePerUnit returns 0
 * because the actual amount varies per Order.
 */
class SustainingMemberDiscountProduct extends Product
{
    public static string $type = 'sustaining_member_discount';

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
        return 'Sustaining member discount';
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
