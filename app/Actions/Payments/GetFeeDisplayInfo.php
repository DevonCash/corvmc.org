<?php

namespace App\Actions\Payments;

use Brick\Money\Money;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFeeDisplayInfo
{
    use AsAction;

    /**
     * Get fee information for display purposes (helper text, tooltips, etc.)
     */
    public function handle(Money $baseAmount): array
    {
        $totalWithCoverage = CalculateTotalWithFeeCoverage::run($baseAmount);
        $actualFeeAmount = $totalWithCoverage->minus($baseAmount);

        return [
            'display_fee' => $actualFeeAmount->getAmount()->toFloat(),
            'total_with_coverage' => $totalWithCoverage->getAmount()->toFloat(),
            'message' => sprintf(
                'Add $%.2f to cover processing fees (2.9%% + $0.30)',
                $actualFeeAmount->getAmount()->toFloat()
            ),
        ];
    }
}
