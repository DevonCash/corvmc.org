<?php

namespace CorvMC\Finance\Models;

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Exceptions\InsufficientCreditsException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * UserCredit - Current State Table (Double-Entry Bookkeeping Pattern)
 *
 * Stores the CURRENT balance for each user's credit type.
 * One row per user per credit type, updated in place.
 *
 * This is the SOURCE OF TRUTH for "what's their balance right now?"
 *
 * Works with CreditTransaction (immutable audit log):
 * - UserCredit.balance is updated in place (fast queries)
 * - CreditTransaction records every change as new rows (audit trail)
 * - CreditTransaction.balance_after snapshots UserCredit.balance after each change
 *
 * Example:
 * - User starts with 0 credits
 * - Monthly allocation adds 16 blocks → UserCredit.balance = 16, CreditTransaction(+16, balance_after: 16)
 * - Reservation uses 4 blocks → UserCredit.balance = 12, CreditTransaction(-4, balance_after: 12)
 *
 * Always query current balance from UserCredit::getBalance(), not from CreditTransaction.
 *
 * @property int $id
 * @property int $user_id
 * @property string $credit_type
 * @property int $balance
 * @property int|null $max_balance
 * @property bool $rollover_enabled
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereMaxBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereRolloverEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCredit whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credit_type',
        'balance',
        'max_balance',
        'rollover_enabled',
        'expires_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'max_balance' => 'integer',
        'rollover_enabled' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get user's current credit balance.
     */
    public static function getBalance(User $user, CreditType $creditType = CreditType::FreeHours): int
    {
        return static::where('user_id', $user->id)
            ->where('credit_type', $creditType->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->value('balance') ?? 0;
    }

    /**
     * Add credits to user's account (transaction-safe).
     *
     * Maintains ledger invariant: Updates UserCredit.balance AND creates CreditTransaction.
     */
    public static function add(
        User $user,
        int $amount,
        CreditType $creditType,
        string $source,
        ?int $sourceId = null,
        ?string $description = null,
        ?Carbon $expiresAt = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $creditType, $source, $sourceId, $description, $expiresAt) {
            // Lock user credit record for update
            $credit = static::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType->value],
                    array_merge(
                        ['balance' => 0],
                        $expiresAt ? ['expires_at' => $expiresAt] : [],
                        static::getDefaultConfig($creditType)
                    )
                );

            // Update balance
            $credit->balance += $amount;
            $credit->save();

            // Record transaction
            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType->value,
                'amount' => $amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Deduct credits from user's account (transaction-safe).
     *
     * Maintains ledger invariant: Updates UserCredit.balance AND creates CreditTransaction.
     * Throws InsufficientCreditsException if balance is insufficient.
     */
    public static function deduct(
        User $user,
        int $amount,
        CreditType $creditType,
        string $source,
        ?int $sourceId = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $creditType, $source, $sourceId) {
            $credit = static::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('credit_type', $creditType->value)
                ->first();

            if (! $credit || $credit->balance < $amount) {
                $currentBalance = $credit->balance ?? 0;
                throw new InsufficientCreditsException(
                    "User has {$currentBalance} credits but needs {$amount}"
                );
            }

            $credit->balance -= $amount;
            $credit->save();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType->value,
                'amount' => -$amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get default configuration for a credit type.
     */
    protected static function getDefaultConfig(CreditType $creditType): array
    {
        return match ($creditType) {
            CreditType::FreeHours => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
            CreditType::EquipmentCredits => [
                'balance' => 0,
                'max_balance' => 250,
                'rollover_enabled' => true,
            ],
        };
    }
}
