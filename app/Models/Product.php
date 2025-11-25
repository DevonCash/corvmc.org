<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Brick\Money\Money $price
 * @property ProductCategory $category
 * @property string|null $sku
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleItem> $saleItems
 * @property-read int|null $sale_items_count
 */
class Product extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'price' => MoneyCast::class.':USD',
        'category' => ProductCategory::class,
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price', 'category', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function saleItems(): MorphMany
    {
        return $this->morphMany(SaleItem::class, 'sellable');
    }
}
