<?php

namespace CorvMC\Finance\Models;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Support\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Charge - Payment tracking for any Chargeable model.
 *
 * Stores pricing, credit application, and payment status for anything
 * that can be charged (reservations, equipment loans, event tickets, etc.)
 *
 * Works with the credits system:
 * - amount: Gross amount before credits (in cents)
 * - credits_applied: Array of credit types and amounts applied {"FreeHours": 4}
 * - net_amount: Amount due after credits (in cents)
 *
 * @property int $id
 * @property int $user_id
 * @property string $chargeable_type
 * @property int $chargeable_id
 * @property \Brick\Money\Money $amount
 * @property array<string, int>|null $credits_applied
 * @property \Brick\Money\Money $net_amount
 * @property ChargeStatus $status
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $stripe_session_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read Chargeable|\Illuminate\Database\Eloquent\Model|null $chargeable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge paid()
 *
 * @mixin \Eloquent
 */
class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chargeable_type',
        'chargeable_id',
        'amount',
        'credits_applied',
        'net_amount',
        'status',
        'payment_method',
        'paid_at',
        'stripe_session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':USD',
            'net_amount' => MoneyCast::class.':USD',
            'credits_applied' => 'array',
            'status' => ChargeStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the user who is responsible for this charge.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the chargeable model (Reservation, EquipmentLoan, etc.)
     */
    public function chargeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a charge for a chargeable entity.
     *
     * @param  Chargeable&Model  $chargeable
     * @param  int  $amount  Gross amount in cents
     * @param  int  $netAmount  Net amount after credits in cents
     * @param  array<string, int>|null  $creditsApplied
     */
    public static function createForChargeable(
        Chargeable $chargeable,
        int $amount,
        int $netAmount,
        ?array $creditsApplied = null
    ): self {
        return self::create([
            'user_id' => $chargeable->getBillableUser()->getKey(),
            'chargeable_type' => $chargeable->getMorphClass(),
            'chargeable_id' => $chargeable->getKey(),
            'amount' => Money::ofMinor($amount, 'USD'),
            'credits_applied' => $creditsApplied,
            'net_amount' => Money::ofMinor($netAmount, 'USD'),
            'status' => ChargeStatus::Pending,
        ]);
    }

    /**
     * Scope to pending charges.
     */
    public function scopePending($query)
    {
        return $query->where('status', ChargeStatus::Pending);
    }

    /**
     * Scope to paid charges.
     */
    public function scopePaid($query)
    {
        return $query->where('status', ChargeStatus::Paid);
    }

    /**
     * Check if this charge requires payment.
     */
    public function requiresPayment(): bool
    {
        return $this->status->requiresPayment() && $this->net_amount->isPositive();
    }

    /**
     * Mark the charge as paid.
     */
    public function markAsPaid(string $paymentMethod, ?string $stripeSessionId = null, ?string $notes = null): self
    {
        $this->update([
            'status' => ChargeStatus::Paid,
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'stripe_session_id' => $stripeSessionId,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Mark the charge as comped (free).
     */
    public function markAsComped(?string $notes = null): self
    {
        $this->update([
            'status' => ChargeStatus::Comped,
            'paid_at' => now(),
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Mark the charge as refunded.
     */
    public function markAsRefunded(?string $notes = null): self
    {
        $this->update([
            'status' => ChargeStatus::Refunded,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Mark the charge as cancelled (never paid).
     */
    public function markAsCancelled(?string $notes = null): self
    {
        $this->update([
            'status' => ChargeStatus::Cancelled,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Get total credits applied (sum of all credit types).
     */
    public function getTotalCreditsApplied(): int
    {
        return array_sum($this->credits_applied ?? []);
    }

    /**
     * Get credits applied for a specific type.
     */
    public function getCreditsAppliedForType(string $creditType): int
    {
        return $this->credits_applied[$creditType] ?? 0;
    }
}
