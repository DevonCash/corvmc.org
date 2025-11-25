<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundSale
{
    use AsAction;

    /**
     * Refund a sale.
     *
     * @param  Sale  $sale  The sale to refund
     * @return Sale
     *
     * @throws \InvalidArgumentException
     */
    public function handle(Sale $sale): Sale
    {
        if ($sale->status === SaleStatus::Refunded) {
            throw new \InvalidArgumentException('Sale has already been refunded');
        }

        $sale->update([
            'status' => SaleStatus::Refunded,
        ]);

        // Activity log will automatically record who refunded it
        activity()
            ->performedOn($sale)
            ->withProperties([
                'refunded_amount' => $sale->total->formatTo('en_US'),
                'original_payment_method' => $sale->payment_method->value,
            ])
            ->log('Sale refunded');

        return $sale->fresh();
    }
}
