<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_amount' => MoneyCast::class . ':USD,currency',
        'total_amount' => MoneyCast::class . ':USD,currency',
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
        if (!$this->base_amount) {
            return null;
        }

        return $this->base_amount->formatTo('en_US');
    }

    /**
     * Get the total amount formatted for display.
     */
    public function getFormattedTotalAmountAttribute(): ?string
    {
        if (!$this->total_amount) {
            return null;
        }

        return $this->total_amount->formatTo('en_US');
    }

    /**
     * Check if this subscription qualifies for sustaining member benefits.
     */
    public function qualifiesForSustainingMember(): bool
    {
        if (!$this->base_amount) {
            return false;
        }

        return $this->base_amount->getAmount()->toFloat() >= 10.00;
    }

    /**
     * Get the number of free hours this subscription grants per month.
     */
    public function getMonthlyFreeHours(): int
    {
        if (!$this->qualifiesForSustainingMember()) {
            return 0;
        }

        // 1 hour per $5 contributed
        $baseAmount = $this->base_amount->getAmount()->toFloat();
        return (int)floor($baseAmount / 5);
    }
}