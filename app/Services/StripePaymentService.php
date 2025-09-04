<?php

namespace App\Services;

class StripePaymentService
{
    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    const STRIPE_RATE = 0.029;

    const STRIPE_FIXED_FEE = 0.30;

    /**
     * Calculate the processing fee for a given amount.
     * This fee should be added to the base amount.
     */
    public function calculateProcessingFee(float $baseAmount): float
    {
        return ($baseAmount * self::STRIPE_RATE) + self::STRIPE_FIXED_FEE;
    }

    /**
     * Calculate the total amount needed to cover both the base amount
     * and the processing fees. This accounts for the fee applying to itself.
     *
     * Formula: Total = (Base + Fixed Fee) / (1 - Rate)
     * This ensures that after Stripe takes their cut, we net the full base amount.
     */
    public function calculateTotalWithFeeCoverage(float $baseAmount): float
    {
        // Calculate what the total needs to be so that after Stripe's fee,
        // we receive the full base amount
        return ($baseAmount + self::STRIPE_FIXED_FEE) / (1 - self::STRIPE_RATE);
    }

    /**
     * Calculate fee breakdown with accurate accounting.
     * Returns both the simple fee (for display) and the actual total needed.
     */
    public function getFeeBreakdown(float $baseAmount, bool $coverFees = false): array
    {
        if (! $coverFees) {
            return [
                'base_amount' => $baseAmount,
                'fee_amount' => 0,
                'total_amount' => $baseAmount,
                'display_fee' => 0,
                'description' => sprintf('$%.2f membership', $baseAmount),
            ];
        }

        $totalWithFeeCoverage = $this->calculateTotalWithFeeCoverage($baseAmount);
        $actualFeeAmount = $totalWithFeeCoverage - $baseAmount;
        $displayFee = $this->calculateProcessingFee($baseAmount);

        return [
            'base_amount' => $baseAmount,
            'fee_amount' => $actualFeeAmount,
            'total_amount' => $totalWithFeeCoverage,
            'display_fee' => $displayFee,
            'description' => sprintf(
                '$%.2f membership + $%.2f processing fees',
                $baseAmount,
                $displayFee
            ),
        ];
    }

    /**
     * Get fee information for display purposes (helper text, tooltips, etc.)
     */
    public function getFeeDisplayInfo(float $baseAmount): array
    {
        $fee = $this->calculateProcessingFee($baseAmount);
        $totalWithCoverage = $this->calculateTotalWithFeeCoverage($baseAmount);

        return [
            'display_fee' => $fee,
            'total_with_coverage' => $totalWithCoverage,
            'message' => sprintf(
                'Add $%.2f to cover Stripe fees (Total: $%.2f)',
                $fee,
                $fee + $baseAmount
            ),
            'accurate_message' => sprintf(
                'Covers processing fees (Total: $%.2f)',
                $totalWithCoverage
            ),
        ];
    }

    /**
     * Format a money amount for display
     */
    public function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    /**
     * Convert dollars to cents for Stripe API
     */
    public function dollarsToStripeAmount(float $dollars): int
    {
        return intval($dollars * 100);
    }

    /**
     * Convert cents from Stripe API to dollars
     */
    public function stripeAmountToDollars(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Calculate what amount we'll actually receive after Stripe processes a payment
     */
    public function calculateNetAmount(float $totalCharged): float
    {
        return $totalCharged - (($totalCharged * self::STRIPE_RATE) + self::STRIPE_FIXED_FEE);
    }

    /**
     * Validate that fee coverage actually results in the base amount being received
     */
    public function validateFeeCoverage(float $baseAmount, float $totalCharged): bool
    {
        $netReceived = $this->calculateNetAmount($totalCharged);
        $tolerance = 0.01; // 1 cent tolerance for rounding

        return abs($netReceived - $baseAmount) <= $tolerance;
    }
}
