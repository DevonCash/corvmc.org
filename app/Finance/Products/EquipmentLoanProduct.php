<?php

namespace App\Finance\Products;

use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Finance Product wrapping EquipmentLoan.
 *
 * EquipmentLoan stores a flat rental_fee (decimal), so billableUnits is 1
 * and pricePerUnit is the rental_fee converted to cents. The security deposit
 * is handled separately (not part of the Product price — it's a hold, not revenue).
 *
 * Eligible for the equipment_credits wallet discount.
 */
class EquipmentLoanProduct extends Product
{
    public static string $type = 'equipment_loan';

    public static ?string $model = EquipmentLoan::class;

    public static function getBillableUnits(Model $model = null): float
    {
        return 1;
    }

    public static function getPricePerUnit(Model $model = null): int
    {
        if (! $model) {
            return 0;
        }

        // rental_fee is stored as decimal (dollars); convert to cents
        return (int) round((float) $model->rental_fee * 100);
    }

    public static function getDescription(Model $model = null): string
    {
        if (! $model) {
            return 'Equipment Loan';
        }

        $equipmentName = $model->equipment?->name ?? 'Equipment';
        $from = $model->reserved_from?->format('M j, Y') ?? 'TBD';
        $due = $model->due_at?->format('M j, Y') ?? 'TBD';

        return "{$equipmentName} loan ({$from} – {$due})";
    }

    public static function getEligibleWallets(Model $model = null): array
    {
        return ['equipment_credits'];
    }

    public static function getUnit(): string
    {
        return 'loan';
    }
}
