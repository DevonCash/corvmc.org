<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    const STRIPE_RATE = 0.029;
    const STRIPE_FIXED_FEE = 0.30;
    /**
     * Mark reservation as paid.
     */
    public function markReservationAsPaid(Reservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        $reservation->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }

    /**
     * Mark reservation as comped.
     */
    public function markReservationAsComped(Reservation $reservation, ?string $notes = null): void
    {
        $reservation->update([
            'payment_status' => 'comped',
            'payment_method' => 'comp',
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }

    /**
     * Mark reservation as refunded.
     */
    public function markReservationAsRefunded(Reservation $reservation, ?string $notes = null): void
    {
        $reservation->update([
            'payment_status' => 'refunded',
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }

    /**
     * Check if reservation is paid.
     */
    public function isReservationPaid(Reservation $reservation): bool
    {
        return $reservation->payment_status === 'paid';
    }

    /**
     * Check if reservation is comped.
     */
    public function isReservationComped(Reservation $reservation): bool
    {
        return $reservation->payment_status === 'comped';
    }

    /**
     * Check if reservation is unpaid.
     */
    public function isReservationUnpaid(Reservation $reservation): bool
    {
        return $reservation->payment_status === 'unpaid';
    }

    /**
     * Check if reservation is refunded.
     */
    public function isReservationRefunded(Reservation $reservation): bool
    {
        return $reservation->payment_status === 'refunded';
    }

    /**
     * Get payment status badge information for UI display.
     */
    public function getPaymentStatusBadge(Reservation $reservation): array
    {
        return match ($reservation->payment_status) {
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'comped' => ['label' => 'Comped', 'color' => 'info'],
            'refunded' => ['label' => 'Refunded', 'color' => 'danger'],
            'unpaid' => ['label' => 'Unpaid', 'color' => 'danger'],
            default => ['label' => 'Unknown', 'color' => 'gray'],
        };
    }

    /**
     * Get formatted cost display for UI.
     */
    public function getCostDisplay(Reservation $reservation): string
    {
        if ($reservation->cost == 0) {
            return 'Free';
        }

        return '$' . number_format($reservation->cost, 2);
    }

    /**
     * Determine if a reservation requires payment.
     */
    public function requiresPayment(Reservation $reservation): bool
    {
        return $reservation->cost > 0 && !$this->isReservationPaid($reservation) && !$this->isReservationComped($reservation);
    }

    /**
     * Calculate total payments received for a reservation.
     */
    public function getTotalPaymentsReceived(Reservation $reservation): float
    {
        return $reservation->transactions()
            ->where('type', 'payment')
            ->sum('amount');
    }

    /**
     * Get outstanding balance for a reservation.
     */
    public function getOutstandingBalance(Reservation $reservation): float
    {
        $totalPaid = $this->getTotalPaymentsReceived($reservation);
        return max(0, $reservation->cost - $totalPaid);
    }

    /**
     * Check if reservation is fully paid.
     */
    public function isFullyPaid(Reservation $reservation): bool
    {
        return $this->getOutstandingBalance($reservation) <= 0;
    }

    // === STRIPE PAYMENT PROCESSING METHODS ===

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
                $actualFeeAmount
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
        $actualFeeAmount = $totalWithCoverage - $baseAmount;

        return [
            'display_fee' => $actualFeeAmount,
            'total_with_coverage' => $totalWithCoverage,
            'message' => sprintf(
                'Add $%.2f to cover fees (Total: $%.2f)',
                $actualFeeAmount,
                $totalWithCoverage
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
