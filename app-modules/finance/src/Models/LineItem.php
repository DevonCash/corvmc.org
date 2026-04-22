<?php

namespace CorvMC\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LineItem — a single line on an Order's purchase description.
 *
 * Each LineItem describes one product or discount. Discount LineItems carry
 * negative amounts. The sum of all LineItems equals Order.total_amount.
 *
 * @property int $id
 * @property int $order_id
 * @property string $product_type Finance product-type string, resolved via Finance registry
 * @property int|null $product_id Populated for model-backed products; null for categories
 * @property string $description Human-readable snapshot at purchase time
 * @property string $unit 'hour', 'ticket', 'fee', 'discount', etc.
 * @property int $unit_price Cents per unit (negative for discounts)
 * @property float $quantity
 * @property int $amount Cents — may be negative for discounts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Order $order
 */
class LineItem extends Model
{
    use HasFactory;

    protected $table = 'order_line_items';

    protected $fillable = [
        'order_id',
        'product_type',
        'product_id',
        'description',
        'unit',
        'unit_price',
        'quantity',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'unit_price' => 'integer',
            'quantity' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Whether this is a discount line (negative amount).
     */
    public function isDiscount(): bool
    {
        return $this->amount < 0;
    }
}
