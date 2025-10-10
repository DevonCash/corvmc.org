<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFeeBreakdown
{
    use AsAction;

    /**
     * Calculate fee breakdown with accurate accounting using Money objects.
     */
    public function handle(Money $baseAmount, bool $coverFees = false): array
    {
        if (! $coverFees) {
            return [
                'base_amount' => $baseAmount->getAmount()->toFloat(),
                'fee_amount' => 0,
                'total_amount' => $baseAmount->getAmount()->toFloat(),
                'display_fee' => 0,
                'description' => sprintf('$%.2f membership', $baseAmount->getAmount()->toFloat()),
            ];
        }

        $totalWithFeeCoverage = CalculateTotalWithFeeCoverage::run($baseAmount);
        $actualFeeAmount = $totalWithFeeCoverage->minus($baseAmount);

        return [
            'base_amount' => $baseAmount->getAmount()->toFloat(),
            'fee_amount' => $actualFeeAmount->getAmount()->toFloat(),
            'total_amount' => $totalWithFeeCoverage->getAmount()->toFloat(),
            'display_fee' => $actualFeeAmount->getAmount()->toFloat(),
            'description' => sprintf(
                '$%.2f membership + $%.2f processing fees',
                $baseAmount->getAmount()->toFloat(),
                $actualFeeAmount->getAmount()->toFloat()
            ),
        ];
    }
}
