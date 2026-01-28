<?php

namespace CorvMC\Finance\Data;

use Brick\Money\Money;
use Spatie\LaravelData\Data;

/**
 * DTO for price calculation results.
 *
 * Returned by CalculatePriceForUser action with complete
 * pricing breakdown including credit application.
 */
class PriceCalculationData extends Data
{
    public function __construct(
        /**
         * Gross amount before credits (in cents).
         */
        public int $amount,

        /**
         * Credits applied by type: {"FreeHours": 4, "EquipmentCredits": 0}
         *
         * @var array<string, int>
         */
        public array $credits_applied,

        /**
         * Net amount after credits (in cents).
         */
        public int $net_amount,

        /**
         * Rate per unit in cents (e.g., 1500 for $15/hour).
         */
        public int $rate,

        /**
         * Unit type for pricing (e.g., "hour", "day").
         */
        public string $unit,

        /**
         * Billable units (e.g., 2.5 hours).
         */
        public float $billable_units,

        /**
         * Whether the user qualifies for credits.
         */
        public bool $credits_eligible,
    ) {}

    /**
     * Get gross amount as Money object.
     */
    public function getAmountAsMoney(): Money
    {
        return Money::ofMinor($this->amount, 'USD');
    }

    /**
     * Get net amount as Money object.
     */
    public function getNetAmountAsMoney(): Money
    {
        return Money::ofMinor($this->net_amount, 'USD');
    }

    /**
     * Get total credits applied (sum of all types).
     */
    public function getTotalCreditsApplied(): int
    {
        return array_sum($this->credits_applied);
    }

    /**
     * Check if credits were applied.
     */
    public function hasCreditsApplied(): bool
    {
        return $this->getTotalCreditsApplied() > 0;
    }

    /**
     * Check if payment is required after credits.
     */
    public function requiresPayment(): bool
    {
        return $this->net_amount > 0;
    }

    /**
     * Get the credit savings as Money object.
     */
    public function getCreditSavings(): Money
    {
        return Money::ofMinor($this->amount - $this->net_amount, 'USD');
    }
}
