<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents input from the Zeffy api Zapier integration.
 * 
 * Used to track donations and purchases, associated with users via email,
 * and potentially linked to other models in the application.
 * 
 * The content field should store the response from Zeffy.
 *
 * @property int $id
 * @property string $transaction_id
 * @property string $email
 * @property string $currency
 * @property string $type
 * @property array<array-key, mixed> $response
 * @property \Illuminate\Support\Carbon $created_at
 * @property string|null $transactionable_type
 * @property int|null $transactionable_id
 * @property int|null $user_id
 * @property \Brick\Money\Money $amount
 * @property-read Model|\Eloquent|null $transactionable
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'email',
        'amount',
        'currency',
        'type',
        'response',
        'user_id',
        'transactionable_type',
        'transactionable_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class . ':USD',
            'response' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    /**
     * Get the owning transactionable model (Reservation, etc.).
     */
    public function transactionable()
    {
        return $this->morphTo();
    }
}
