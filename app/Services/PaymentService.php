<?php

namespace App\Services;

use App\Models\Reservation;
use Brick\Money\Money;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Stripe processing fee: 2.9% + $0.30 for cards
     */
    const STRIPE_RATE = 0.029;
    const STRIPE_FIXED_FEE_CENTS = 30;
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
        if ($reservation->cost->isZero()) {
            return 'Free';
        }

        return $reservation->cost->formatTo('en_US');
    }

    /**
     * Determine if a reservation requires payment.
     */
    public function requiresPayment(Reservation $reservation): bool
    {
        return $reservation->cost->isPositive() && !$this->isReservationPaid($reservation) && !$this->isReservationComped($reservation);
    }

    /**
     * Calculate total payments received for a reservation.
     */
    public function getTotalPaymentsReceived(Reservation $reservation): Money
    {
        $transactions = $reservation->transactions()
            ->where('type', 'payment')
            ->get();

        $totalCents = $transactions->sum(fn($transaction) => $transaction->amount->getMinorAmount()->toInt());

        return Money::ofMinor($totalCents, 'USD');
    }

    /**
     * Get outstanding balance for a reservation.
     */
    public function getOutstandingBalance(Reservation $reservation): Money
    {
        $totalPaid = $this->getTotalPaymentsReceived($reservation);
        $outstanding = $reservation->cost->minus($totalPaid);

        return $outstanding->isPositive() ? $outstanding : Money::zero('USD');
    }

    /**
     * Check if reservation is fully paid.
     */
    public function isFullyPaid(Reservation $reservation): bool
    {
        return $this->getOutstandingBalance($reservation)->isZero();
    }

    // === STRIPE PAYMENT PROCESSING METHODS ===

    /**
     * Calculate the processing fee for a given Money amount.
     */
    public function calculateProcessingFee(Money $baseAmount): Money
    {
        $percentageFee = $baseAmount->multipliedBy(self::STRIPE_RATE, \Brick\Math\RoundingMode::HALF_UP);
        $fixedFee = Money::ofMinor(self::STRIPE_FIXED_FEE_CENTS, 'USD');

        return $percentageFee->plus($fixedFee);
    }

    /**
     * Calculate the total amount needed to cover both the base amount
     * and the processing fees. This accounts for the fee applying to itself.
     *
     * Formula: Total = (Base + Fixed Fee) / (1 - Rate)
     * This ensures that after Stripe takes their cut, we net the full base amount.
     */
    public function calculateTotalWithFeeCoverage(Money $baseAmount): Money
    {
        $fixedFee = Money::ofMinor(self::STRIPE_FIXED_FEE_CENTS, 'USD');
        $numerator = $baseAmount->plus($fixedFee);
        $denominator = 1 - self::STRIPE_RATE;

        return $numerator->dividedBy($denominator, \Brick\Math\RoundingMode::HALF_UP);
    }

    /**
     * Calculate fee breakdown with accurate accounting using Money objects.
     */
    public function getFeeBreakdown(Money $baseAmount, bool $coverFees = false): array
    {
        if (! $coverFees) {
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
     * Get fee information for display purposes (helper text, tooltips, etc.)
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
     * Format a money amount for display
     */
    public function formatMoney(Money $amount): string
    {
        return $amount->formatTo('en_US');
    }

    /**
     * Convert Money to cents for Stripe API
     */
    public function toStripeAmount(Money $amount): int
    {
        return $amount->getMinorAmount()->toInt();
    }

    /**
     * Convert cents from Stripe API to Money
     */
    public function fromStripeAmount(int $cents): Money
    {
        return Money::ofMinor($cents, 'USD');
    }

    /**
     * Calculate what amount we'll actually receive after Stripe processes a payment
     */
    public function calculateNetAmount(Money $totalCharged): Money
    {
        $processingFee = $this->calculateProcessingFee($totalCharged);
        return $totalCharged->minus($processingFee);
    }

    /**
     * Validate that fee coverage actually results in the base amount being received
     */
    public function validateFeeCoverage(Money $baseAmount, Money $totalCharged): bool
    {
        $netReceived = $this->calculateNetAmount($totalCharged);
        $tolerance = Money::ofMinor(1, 'USD'); // 1 cent tolerance for rounding

        return $netReceived->minus($baseAmount)->abs()->isLessThanOrEqualTo($tolerance);
    }
}
