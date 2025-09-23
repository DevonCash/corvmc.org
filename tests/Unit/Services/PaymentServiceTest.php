<?php

use App\Models\Reservation;
use App\Facades\PaymentService;
use Brick\Money\Money;

describe('Payment Status Management', function () {
    it('can mark reservation as paid', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 45.00,
            'payment_status' => 'unpaid',
        ]);

        PaymentService::markReservationAsPaid($reservation, 'stripe', 'Test payment');

        expect($reservation->fresh())
            ->payment_status->toBe('paid')
            ->payment_method->toBe('stripe')
            ->payment_notes->toBe('Test payment')
            ->paid_at->not->toBeNull();
    });

    it('can mark reservation as paid without optional parameters', function () {
        $reservation = Reservation::factory()->create([
            'payment_status' => 'unpaid',
        ]);

        PaymentService::markReservationAsPaid($reservation);

        expect($reservation->fresh())
            ->payment_status->toBe('paid')
            ->payment_method->toBeNull()
            ->payment_notes->toBeNull()
            ->paid_at->not->toBeNull();
    });

    it('can mark reservation as comped', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 30.00,
            'payment_status' => 'unpaid',
        ]);

        PaymentService::markReservationAsComped($reservation, 'Staff discount');

        expect($reservation->fresh())
            ->payment_status->toBe('comped')
            ->payment_method->toBe('comp')
            ->payment_notes->toBe('Staff discount')
            ->paid_at->not->toBeNull();
    });

    it('can mark reservation as comped without notes', function () {
        $reservation = Reservation::factory()->create([
            'payment_status' => 'unpaid',
        ]);

        PaymentService::markReservationAsComped($reservation);

        expect($reservation->fresh())
            ->payment_status->toBe('comped')
            ->payment_method->toBe('comp')
            ->payment_notes->toBeNull()
            ->paid_at->not->toBeNull();
    });

    it('can mark reservation as refunded', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 60.00,
            'payment_status' => 'paid',
        ]);

        PaymentService::markReservationAsRefunded($reservation, 'Customer request');

        expect($reservation->fresh())
            ->payment_status->toBe('refunded')
            ->payment_notes->toBe('Customer request')
            ->paid_at->not->toBeNull();
    });

    it('can mark reservation as refunded without notes', function () {
        $reservation = Reservation::factory()->create([
            'payment_status' => 'paid',
        ]);

        PaymentService::markReservationAsRefunded($reservation);

        expect($reservation->fresh())
            ->payment_status->toBe('refunded')
            ->payment_notes->toBeNull()
            ->paid_at->not->toBeNull();
    });
});

describe('Payment Status Checking', function () {
    it('can check if reservation is paid', function () {
        $paidReservation = Reservation::factory()->create(['payment_status' => 'paid']);
        $unpaidReservation = Reservation::factory()->create(['payment_status' => 'unpaid']);

        expect(PaymentService::isReservationPaid($paidReservation))->toBeTrue();
        expect(PaymentService::isReservationPaid($unpaidReservation))->toBeFalse();
    });

    it('can check if reservation is comped', function () {
        $compedReservation = Reservation::factory()->create(['payment_status' => 'comped']);
        $unpaidReservation = Reservation::factory()->create(['payment_status' => 'unpaid']);

        expect(PaymentService::isReservationComped($compedReservation))->toBeTrue();
        expect(PaymentService::isReservationComped($unpaidReservation))->toBeFalse();
    });

    it('can check if reservation is unpaid', function () {
        $unpaidReservation = Reservation::factory()->create(['payment_status' => 'unpaid']);
        $paidReservation = Reservation::factory()->create(['payment_status' => 'paid']);

        expect(PaymentService::isReservationUnpaid($unpaidReservation))->toBeTrue();
        expect(PaymentService::isReservationUnpaid($paidReservation))->toBeFalse();
    });

    it('can check if reservation is refunded', function () {
        $refundedReservation = Reservation::factory()->create(['payment_status' => 'refunded']);
        $paidReservation = Reservation::factory()->create(['payment_status' => 'paid']);

        expect(PaymentService::isReservationRefunded($refundedReservation))->toBeTrue();
        expect(PaymentService::isReservationRefunded($paidReservation))->toBeFalse();
    });
});

describe('UI Display Helpers', function () {
    it('can get payment status badge for paid reservation', function () {
        $reservation = Reservation::factory()->create(['payment_status' => 'paid']);

        $badge = PaymentService::getPaymentStatusBadge($reservation);

        expect($badge)
            ->toBe(['label' => 'Paid', 'color' => 'success']);
    });

    it('can get payment status badge for comped reservation', function () {
        $reservation = Reservation::factory()->create(['payment_status' => 'comped']);

        $badge = PaymentService::getPaymentStatusBadge($reservation);

        expect($badge)
            ->toBe(['label' => 'Comped', 'color' => 'info']);
    });

    it('can get payment status badge for refunded reservation', function () {
        $reservation = Reservation::factory()->create(['payment_status' => 'refunded']);

        $badge = PaymentService::getPaymentStatusBadge($reservation);

        expect($badge)
            ->toBe(['label' => 'Refunded', 'color' => 'danger']);
    });

    it('can get payment status badge for unpaid reservation', function () {
        $reservation = Reservation::factory()->create(['payment_status' => 'unpaid']);

        $badge = PaymentService::getPaymentStatusBadge($reservation);

        expect($badge)
            ->toBe(['label' => 'Unpaid', 'color' => 'danger']);
    });

    it('can get payment status badge for unknown status', function () {
        $reservation = Reservation::factory()->create(['payment_status' => 'invalid']);

        $badge = PaymentService::getPaymentStatusBadge($reservation);

        expect($badge)
            ->toBe(['label' => 'Unknown', 'color' => 'gray']);
    });

    it('can get cost display for free reservation', function () {
        $reservation = Reservation::factory()->create(['cost' => 0]);

        $display = PaymentService::getCostDisplay($reservation);

        expect($display)->toBe('Free');
    });

    it('can get cost display for paid reservation', function () {
        $reservation = Reservation::factory()->create(['cost' => 45.50]);

        $display = PaymentService::getCostDisplay($reservation);

        expect($display)->toBe('$45.50');
    });

    it('can get cost display with proper formatting', function () {
        $reservation = Reservation::factory()->create(['cost' => 100]);

        $display = PaymentService::getCostDisplay($reservation);

        expect($display)->toBe('$100.00');
    });

    it('can format money amounts', function () {
        expect(PaymentService::formatMoney(Money::of(45.50, 'USD')))->toBe('$45.50');
        expect(PaymentService::formatMoney(Money::of(100, 'USD')))->toBe('$100.00');
        expect(PaymentService::formatMoney(Money::of(0, 'USD')))->toBe('$0.00');
        expect(PaymentService::formatMoney(Money::of(999.99, 'USD')))->toBe('$999.99');
    });
});

describe('Payment Logic', function () {
    it('determines if reservation requires payment when cost is positive and unpaid', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 30.00,
            'payment_status' => 'unpaid'
        ]);

        expect(PaymentService::requiresPayment($reservation))->toBeTrue();
    });

    it('determines no payment required when cost is zero', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 0,
            'payment_status' => 'unpaid'
        ]);

        expect(PaymentService::requiresPayment($reservation))->toBeFalse();
    });

    it('determines no payment required when already paid', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 30.00,
            'payment_status' => 'paid'
        ]);

        expect(PaymentService::requiresPayment($reservation))->toBeFalse();
    });

    it('determines no payment required when comped', function () {
        $reservation = Reservation::factory()->create([
            'cost' => 30.00,
            'payment_status' => 'comped'
        ]);

        expect(PaymentService::requiresPayment($reservation))->toBeFalse();
    });

    // Transaction-related payment tests removed since Transaction model was removed
});

describe('Stripe Integration Calculations', function () {
    it('can calculate processing fee', function () {
        // Test with $100 base amount
        // Expected: (100 * 0.029) + 0.30 = 2.90 + 0.30 = 3.20
        $fee = PaymentService::calculateProcessingFee(Money::of(100.00, 'USD'));
        expect($fee->getAmount()->toFloat())->toBe(3.20);

        // Test with $10 base amount
        // Expected: (10 * 0.029) + 0.30 = 0.29 + 0.30 = 0.59
        $fee = PaymentService::calculateProcessingFee(Money::of(10.00, 'USD'));
        expect(round($fee->getAmount()->toFloat(), 2))->toBe(0.59);
    });

    it('can calculate total with fee coverage', function () {
        // For $100 base amount with fee coverage
        // Formula: (100 + 0.30) / (1 - 0.029) = 100.30 / 0.971 ≈ 103.30
        $total = PaymentService::calculateTotalWithFeeCoverage(Money::of(100.00, 'USD'));
        expect(round($total->getAmount()->toFloat(), 2))->toBe(103.30);

        // For $10 base amount
        // Formula: (10 + 0.30) / (1 - 0.029) = 10.30 / 0.971 ≈ 10.61
        $total = PaymentService::calculateTotalWithFeeCoverage(Money::of(10.00, 'USD'));
        expect(round($total->getAmount()->toFloat(), 2))->toBe(10.61);
    });

    it('can get fee breakdown without coverage', function () {
        $breakdown = PaymentService::getFeeBreakdown(Money::of(50.00, 'USD'), false);

        expect($breakdown)->toBe([
            'base_amount' => 50.00,
            'fee_amount' => 0,
            'total_amount' => 50.00,
            'display_fee' => 0,
            'description' => '$50.00 membership',
        ]);
    });

    it('can get fee breakdown with coverage', function () {
        $baseAmount = Money::of(50.00, 'USD');
        $breakdown = PaymentService::getFeeBreakdown($baseAmount, true);

        expect($breakdown)
            ->toHaveKey('base_amount', 50.00)
            ->toHaveKey('total_amount')
            ->toHaveKey('fee_amount')
            ->toHaveKey('display_fee')
            ->toHaveKey('description');

        // Verify the total is calculated correctly
        $expectedTotal = PaymentService::calculateTotalWithFeeCoverage($baseAmount);
        expect($breakdown['total_amount'])->toBe($expectedTotal->getAmount()->toFloat());

        // Verify fee amount is the difference
        $expectedFeeAmount = $expectedTotal->minus($baseAmount);
        expect($breakdown['fee_amount'])->toBe($expectedFeeAmount->getAmount()->toFloat());
    });

    it('can get fee display info', function () {
        $baseAmount = Money::of(25.00, 'USD');
        $info = PaymentService::getFeeDisplayInfo($baseAmount);
        $expectedTotal = PaymentService::calculateTotalWithFeeCoverage($baseAmount);
        $expectedFee = $expectedTotal->minus($baseAmount);

        expect($info)
            ->toHaveKey('display_fee', $expectedFee->getAmount()->toFloat())
            ->toHaveKey('total_with_coverage', $expectedTotal->getAmount()->toFloat())
            ->toHaveKey('message');

        expect($info['message'])->toContain('Add $')
            ->and($info['message'])->toContain('processing fees');
    });

    it('can convert dollars to stripe amount', function () {
        expect(PaymentService::toStripeAmount(Money::of(45.50, 'USD')))->toBe(4550);
        expect(PaymentService::toStripeAmount(Money::of(100.00, 'USD')))->toBe(10000);
        expect(PaymentService::toStripeAmount(Money::of(0.99, 'USD')))->toBe(99);
    });

    it('can convert stripe amount to dollars', function () {
        expect(PaymentService::fromStripeAmount(4550)->getAmount()->toFloat())->toBe(45.50);
        expect(PaymentService::fromStripeAmount(10000)->getAmount()->toFloat())->toBe(100.00);
        expect(PaymentService::fromStripeAmount(99)->getAmount()->toFloat())->toBe(0.99);
    });

    it('can calculate net amount after stripe fees', function () {
        // For $100 charged, expect to net: 100 - (100 * 0.029 + 0.30) = 100 - 3.20 = 96.80
        $net = PaymentService::calculateNetAmount(Money::of(100.00, 'USD'));
        expect($net->getAmount()->toFloat())->toBe(96.80);

        // For $10 charged, expect to net: 10 - (10 * 0.029 + 0.30) = 10 - 0.59 = 9.41
        $net = PaymentService::calculateNetAmount(Money::of(10.00, 'USD'));
        expect($net->getAmount()->toFloat())->toBe(9.41);
    });

    it('can validate fee coverage accuracy', function () {
        $baseAmount = Money::of(50.00, 'USD');
        $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);

        // The coverage calculation should be accurate
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage))->toBeTrue();

        // Insufficient coverage should fail validation
        expect(PaymentService::validateFeeCoverage($baseAmount, $baseAmount))->toBeFalse();

        // Over-coverage should pass (within tolerance)
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage->plus(Money::ofMinor(1, 'USD'))))->toBeTrue();
    });

    it('validates fee coverage with tolerance for rounding', function () {
        $baseAmount = Money::of(33.33, 'USD');
        $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);

        // Should be valid within 1 cent tolerance
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage->plus(Money::ofMinor(1, 'USD'))))->toBeTrue();

        // Should fail outside tolerance
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage->minus(Money::ofMinor(2, 'USD'))))->toBeFalse();
    });
});
