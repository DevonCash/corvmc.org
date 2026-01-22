<?php

namespace CorvMC\Finance\Concerns;

use CorvMC\Finance\Models\Charge;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * HasCharges Trait
 *
 * Provides charge relationship to Chargeable models.
 * Models using this trait should implement the Chargeable interface.
 */
trait HasCharges
{
    /**
     * Get the charge for this chargeable.
     */
    public function charge(): MorphOne
    {
        return $this->morphOne(Charge::class, 'chargeable');
    }

    /**
     * Check if this chargeable requires payment.
     *
     * Delegates to the charge record if it exists.
     */
    public function requiresChargePayment(): bool
    {
        return $this->charge?->requiresPayment() ?? false;
    }

    /**
     * Get the gross amount (before credits) for this chargeable.
     */
    public function getGrossAmount(): ?\Brick\Money\Money
    {
        return $this->charge?->amount;
    }

    /**
     * Get the net amount (after credits) for this chargeable.
     */
    public function getNetAmount(): ?\Brick\Money\Money
    {
        return $this->charge?->net_amount;
    }

    /**
     * Get the payment status for this chargeable.
     */
    public function getChargeStatus(): ?\CorvMC\Finance\Enums\ChargeStatus
    {
        return $this->charge?->status;
    }

    /**
     * Check if payment is settled (paid, comped, or refunded).
     */
    public function isPaymentSettled(): bool
    {
        return $this->charge?->status?->isSettled() ?? false;
    }
}
