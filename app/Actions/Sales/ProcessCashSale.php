<?php

namespace App\Actions\Sales;

use App\Enums\SalePaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessCashSale
{
    use AsAction;

    /**
     * Process a cash sale.
     *
     * @param  array  $cartItems  Array of ['product_id' => quantity]
     * @param  int  $tenderedAmountCents  Amount customer gave in cents
     * @param  User|null  $customer  Optional member to associate sale with
     * @param  string|null  $notes  Optional sale notes
     * @return Sale
     *
     * @throws \InvalidArgumentException
     */
    public function handle(
        array $cartItems,
        int $tenderedAmountCents,
        ?User $customer = null,
        ?string $notes = null
    ): Sale {
        if (empty($cartItems)) {
            throw new \InvalidArgumentException('Cart cannot be empty');
        }

        return DB::transaction(function () use ($cartItems, $tenderedAmountCents, $customer, $notes) {
            // Load all products
            $productIds = array_keys($cartItems);
            $products = Product::whereIn('id', $productIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            // Calculate totals
            $subtotalCents = 0;
            $saleItemsData = [];

            foreach ($cartItems as $productId => $quantity) {
                if (! isset($products[$productId])) {
                    throw new \InvalidArgumentException("Product {$productId} not found or inactive");
                }

                if ($quantity <= 0) {
                    throw new \InvalidArgumentException('Quantity must be greater than 0');
                }

                $product = $products[$productId];
                $unitPriceCents = $product->price->getMinorAmount()->toInt();
                $itemSubtotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $itemSubtotalCents;

                $saleItemsData[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPriceCents,
                    'subtotal' => $itemSubtotalCents,
                ];
            }

            // For now, no tax. Can add tax calculation later.
            $taxCents = 0;
            $totalCents = $subtotalCents + $taxCents;

            // Validate tendered amount
            if ($tenderedAmountCents < $totalCents) {
                $total = Money::ofMinor($totalCents, 'USD');
                $tendered = Money::ofMinor($tenderedAmountCents, 'USD');
                throw new \InvalidArgumentException(
                    "Insufficient payment. Total: {$total->formatTo('en_US')}, Tendered: {$tendered->formatTo('en_US')}"
                );
            }

            $changeCents = $tenderedAmountCents - $totalCents;

            // Create sale
            $sale = Sale::create([
                'user_id' => $customer?->id,
                'subtotal' => $subtotalCents,
                'tax' => $taxCents,
                'total' => $totalCents,
                'payment_method' => SalePaymentMethod::Cash,
                'status' => SaleStatus::Completed,
                'tendered_amount' => $tenderedAmountCents,
                'change_amount' => $changeCents,
                'notes' => $notes,
            ]);

            // Create sale items
            foreach ($saleItemsData as $itemData) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'sellable_type' => Product::class,
                    'sellable_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                    'description' => $itemData['product']->name,
                ]);
            }

            // Reload with relationships
            return $sale->load(['items', 'user']);
        });
    }
}
