<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Processing fee added to Orders paid by card.
 *
 * Category product (no backing model). Price is computed from the config
 * rate_bps + fixed_cents applied to the Order subtotal at commit time.
 * The actual amount is set when the LineItem is built — pricePerUnit here
 * returns the fixed portion only; the percentage is applied by PricingService.
 */
class ProcessingFeeProduct extends Product
{
    public static string $type = 'processing_fee';

    public static ?string $model = null;

    public static function getBillableUnits(?Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(?Model $model = null): int
    {
        return (int) config('finance.processing_fee.fixed_cents', 30);
    }

    public static function getDescription(?Model $model = null): string
    {
        return 'Card processing fee';
    }

    public static function getEligibleWallets(?Model $model = null): array
    {
        return [];
    }

    public static function getUnit(): string
    {
        return 'fee';
    }

    /**
     * Compute the exact pass-through processing fee for a given subtotal in cents.
     *
     * The goal is that after Stripe takes its cut from the total charge,
     * the merchant receives exactly $subtotalCents.
     *
     * charge = ceil((subtotal + fixed) / (1 - rate))
     * fee   = charge - subtotal
     */
    public static function computeFee(int $subtotalCents): int
    {
        $rateBps = (int) config('finance.processing_fee.rate_bps', 290);
        $fixedCents = (int) config('finance.processing_fee.fixed_cents', 30);

        $rate = $rateBps / 10000;
        $charge = (int) ceil(($subtotalCents + $fixedCents) / (1 - $rate));

        return $charge - $subtotalCents;
    }
}
