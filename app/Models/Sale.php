<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SalePaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $user_id
 * @property \Brick\Money\Money $subtotal
 * @property \Brick\Money\Money $tax
 * @property \Brick\Money\Money $total
 * @property SalePaymentMethod $payment_method
 * @property SaleStatus $status
 * @property \Brick\Money\Money|null $tendered_amount
 * @property \Brick\Money\Money|null $change_amount
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleItem> $items
 * @property-read int|null $items_count
 */
class Sale extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'subtotal',
        'tax',
        'total',
        'payment_method',
        'status',
        'tendered_amount',
        'change_amount',
        'notes',
    ];

    protected $casts = [
        'subtotal' => MoneyCast::class.':USD',
        'tax' => MoneyCast::class.':USD',
        'total' => MoneyCast::class.':USD',
        'tendered_amount' => MoneyCast::class.':USD',
        'change_amount' => MoneyCast::class.':USD',
        'payment_method' => SalePaymentMethod::class,
        'status' => SaleStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'total', 'payment_method', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
