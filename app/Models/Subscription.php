<?php

namespace App\Models;

use CorvMC\Support\Casts\MoneyCast;
use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $stripe_id
 * @property string $stripe_status
 * @property string|null $stripe_price
 * @property int|null $quantity
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Brick\Money\Money|null $base_amount
 * @property \Brick\Money\Money|null $total_amount
 * @property string $currency
 * @property bool $covers_fees
 * @property array<array-key, mixed>|null $metadata
 * @property-read string|null $formatted_base_amount
 * @property-read string|null $formatted_total_amount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Cashier\SubscriptionItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User|null $owner
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription canceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription ended()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription expiredTrial()
 * @method static \Laravel\Cashier\Database\Factories\SubscriptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription incomplete()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription notCanceled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription notOnGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription notOnTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription onGracePeriod()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription onTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription pastDue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription recurring()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCoversFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStripePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStripeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Subscription extends CashierSubscription
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_amount' => MoneyCast::class.':USD,currency',
        'total_amount' => MoneyCast::class.':USD,currency',
        'covers_fees' => 'boolean',
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'base_amount',
        'total_amount',
        'currency',
        'covers_fees',
        'metadata',
        'trial_ends_at',
        'ends_at',
    ];

    /**
     * Get the base amount formatted for display.
     */
    public function getFormattedBaseAmountAttribute(): ?string
    {
        if (! $this->base_amount) {
            return null;
        }

        return $this->base_amount->formatTo('en_US');
    }

    /**
     * Get the total amount formatted for display.
     */
    public function getFormattedTotalAmountAttribute(): ?string
    {
        if (! $this->total_amount) {
            return null;
        }

        return $this->total_amount->formatTo('en_US');
    }

    /**
     * Check if this subscription qualifies for sustaining member benefits.
     */
    public function qualifiesForSustainingMember(): bool
    {
        if (! $this->base_amount) {
            return false;
        }

        return $this->base_amount->getAmount()->toFloat() >= 10.00;
    }

    /**
     * Get the number of free hours this subscription grants per month.
     */
    public function getMonthlyFreeHours(): int
    {
        if (! $this->qualifiesForSustainingMember()) {
            return 0;
        }

        // 1 hour per $5 contributed
        $baseAmount = $this->base_amount->getAmount()->toFloat();

        return (int) floor($baseAmount / 5);
    }
}
