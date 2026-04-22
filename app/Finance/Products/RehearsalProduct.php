<?php

namespace App\Finance\Products;

use CorvMC\Finance\Products\Product;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Eloquent\Model;

/**
 * Finance Product wrapping RehearsalReservation.
 *
 * Pulls pricing from config. One billable unit = one hour of rehearsal time.
 * Eligible for the free_hours wallet discount.
 */
class RehearsalProduct extends Product
{
    public static string $type = 'rehearsal_time';

    public static ?string $model = RehearsalReservation::class;

    public static function billableUnits(Model $model = null): float
    {
        if (! $model || ! $model->reserved_at || ! $model->reserved_until) {
            return 0;
        }

        return $model->reserved_at->diffInMinutes($model->reserved_until) / 60;
    }

    public static function pricePerUnit(Model $model = null): int
    {
        return (int) config('finance.pricing.' . RehearsalReservation::class . '.rate', 1500);
    }

    public static function description(Model $model = null): string
    {
        if (! $model) {
            return 'Practice Space';
        }

        $hours = static::billableUnits($model);
        $date = $model->reserved_at?->format('M j, Y') ?? 'TBD';

        return "Practice Space - {$hours} hour(s) on {$date}";
    }

    public static function eligibleWallets(Model $model = null): array
    {
        return ['free_hours'];
    }

    public static function unit(): string
    {
        return 'hour';
    }
}
