<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property string|null $sellable_type
 * @property int|null $sellable_id
 * @property int $quantity
 * @property \Brick\Money\Money $unit_price
 * @property \Brick\Money\Money $subtotal
 * @property string $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Sale $sale
 * @property-read \Illuminate\Database\Eloquent\Model|null $sellable
 */
class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'sellable_type',
        'sellable_id',
        'quantity',
        'unit_price',
        'subtotal',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => MoneyCast::class.':USD',
        'subtotal' => MoneyCast::class.':USD',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function sellable(): MorphTo
    {
        return $this->morphTo();
    }
}
