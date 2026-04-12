<?php

namespace CorvMC\Finance\Services;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

/**
 * Service for managing payment processing fees and calculations.
 * 
 * This service handles all fee-related calculations for Stripe payments,
 * including fee coverage calculations that ensure the full amount is received
 * after processing fees are deducted.
 */
class FeeService
{
    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    public const STRIPE_RATE = 0.029;
    
    public const STRIPE_FIXED_FEE_CENTS = 30;

    /**
     * Calculate the processing fee for a given Money amount.
     * 
     * @param Money $baseAmount The amount to calculate the fee for
     * @return Money The processing fee amount
     */
    public function calculateProcessingFee(Money $baseAmount): Money
    {
        $percentageFee = $baseAmount->multipliedBy(self::STRIPE_RATE, RoundingMode::HALF_UP);
        $fixedFee = Money::ofMinor(self::STRIPE_FIXED_FEE_CENTS, 'USD');

        return $percentageFee->plus($fixedFee);
    }

    /**
     * Calculate the total amount needed to cover both the base amount
     * and the processing fees. This accounts for the fee applying to itself.
     *
     * Formula: Total = (Base + Fixed Fee) / (1 - Rate)
     * This ensures that after Stripe takes their cut, we net the full base amount.
     * 
     * @param Money $baseAmount The desired net amount to receive
     * @return Money The total amount to charge to receive the base amount after fees
     */
    public function calculateTotalWithFeeCoverage(Money $baseAmount): Money
    {
        $fixedFee = Money::ofMinor(self::STRIPE_FIXED_FEE_CENTS, 'USD');
        $numerator = $baseAmount->plus($fixedFee);
        $denominator = 1 - self::STRIPE_RATE;

        return $numerator->dividedBy($denominator, RoundingMode::HALF_UP);
    }

    /**
     * Calculate the fee coverage amount for a base amount in cents.
     * 
     * @param int $baseAmountCents The base amount in cents
     * @return Money The fee coverage amount
     */
    public function calculateFeeCoverage(int $baseAmountCents): Money
    {
        $baseAmount = Money::ofMinor($baseAmountCents, 'USD');
        $totalWithCoverage = $this->calculateTotalWithFeeCoverage($baseAmount);

        return $totalWithCoverage->minus($baseAmount);
    }

    /**
     * Calculate what amount we'll actually receive after Stripe processes a payment.
     * 
     * @param Money $totalCharged The total amount charged to the customer
     * @return Money The net amount received after processing fees
     */
    public function calculateNetAmount(Money $totalCharged): Money
    {
        $processingFee = $this->calculateProcessingFee($totalCharged);

        return $totalCharged->minus($processingFee);
    }

    /**
     * Validate that fee coverage actually results in the base amount being received.
     * 
     * @param Money $baseAmount The expected net amount to receive
     * @param Money $totalCharged The total amount charged to the customer
     * @return bool True if the net amount is within 1 cent of the base amount
     */
    public function validateFeeCoverage(Money $baseAmount, Money $totalCharged): bool
    {
        $netReceived = $this->calculateNetAmount($totalCharged);
        $tolerance = Money::ofMinor(1, 'USD'); // 1 cent tolerance for rounding

        return $netReceived->minus($baseAmount)->abs()->isLessThanOrEqualTo($tolerance);
    }

    /**
     * Calculate fee breakdown with accurate accounting using Money objects.
     * 
     * @param Money $baseAmount The base amount before fees
     * @param bool $coverFees Whether to include fee coverage
     * @return array{
     *     base_amount: float,
     *     fee_amount: float,
     *     total_amount: float,
     *     display_fee: float,
     *     description: string
     * }
     */
    public function getFeeBreakdown(Money $baseAmount, bool $coverFees = false): array
    {
        if (!$coverFees) {
            return [
                'base_amount' => $baseAmount->getAmount()->toFloat(),
                'fee_amount' => 0,
                'total_amount' => $baseAmount->getAmount()->toFloat(),
                'display_fee' => 0,
                'description' => sprintf('$%.2f membership', $baseAmount->getAmount()->toFloat()),
            ];
        }

        $totalWithFeeCoverage = $this->calculateTotalWithFeeCoverage($baseAmount);
        $actualFeeAmount = $totalWithFeeCoverage->minus($baseAmount);

        return [
            'base_amount' => $baseAmount->getAmount()->toFloat(),
            'fee_amount' => $actualFeeAmount->getAmount()->toFloat(),
            'total_amount' => $totalWithFeeCoverage->getAmount()->toFloat(),
            'display_fee' => $actualFeeAmount->getAmount()->toFloat(),
            'description' => sprintf(
                '$%.2f membership + $%.2f processing fees',
                $baseAmount->getAmount()->toFloat(),
                $actualFeeAmount->getAmount()->toFloat()
            ),
        ];
    }

    /**
     * Get fee information for display purposes (helper text, tooltips, etc.).
     * 
     * @param Money $baseAmount The base amount before fees
     * @return array{
     *     display_fee: float,
     *     total_with_coverage: float,
     *     message: string
     * }
     */
    public function getFeeDisplayInfo(Money $baseAmount): array
    {
        $totalWithCoverage = $this->calculateTotalWithFeeCoverage($baseAmount);
        $actualFeeAmount = $totalWithCoverage->minus($baseAmount);

        return [
            'display_fee' => $actualFeeAmount->getAmount()->toFloat(),
            'total_with_coverage' => $totalWithCoverage->getAmount()->toFloat(),
            'message' => sprintf(
                'Add $%.2f to cover processing fees (2.9%% + $0.30)',
                $actualFeeAmount->getAmount()->toFloat()
            ),
        ];
    }

    /**
     * Mark a reservation as refunded.
     * 
     * @param RehearsalReservation $reservation The reservation to mark as refunded
     * @param string|null $notes Optional refund notes
     */
    public function markReservationAsRefunded(RehearsalReservation $reservation, ?string $notes = null): void
    {
        $reservation->charge?->markAsRefunded($notes);
    }
}