<?php

use App\Models\Reservation;
use App\Facades\PaymentService;

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
        expect(PaymentService::formatMoney(45.50))->toBe('$45.50');
        expect(PaymentService::formatMoney(100))->toBe('$100.00');
        expect(PaymentService::formatMoney(0))->toBe('$0.00');
        expect(PaymentService::formatMoney(999.99))->toBe('$999.99');
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

    it('can calculate total payments received', function () {
        $reservation = Reservation::factory()->create();

        // Create payment transactions
        $reservation->transactions()->create([
            'transaction_id' => 'test_txn_1',
            'type' => 'payment',
            'amount' => 25.00,
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
            'response' => ['test' => 'data'],
        ]);

        $reservation->transactions()->create([
            'transaction_id' => 'test_txn_2',
            'type' => 'payment',
            'amount' => 15.00,
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
            'response' => ['test' => 'data'],
        ]);

        // Create non-payment transaction (should not be counted)
        $reservation->transactions()->create([
            'transaction_id' => 'test_txn_3',
            'type' => 'refund',
            'amount' => 10.00,
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
            'response' => ['test' => 'data'],
        ]);

        $total = PaymentService::getTotalPaymentsReceived($reservation);

        expect($total)->toBe(40.00);
    });

    it('can calculate outstanding balance', function () {
        $reservation = Reservation::factory()->create(['cost' => 60.00]);

        $reservation->transactions()->create([
            'transaction_id' => 'test_balance_1',
            'type' => 'payment',
            'amount' => 25.00,
            'status' => 'completed',
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
        ]);

        $balance = PaymentService::getOutstandingBalance($reservation);

        expect($balance)->toBe(35.00);
    });

    it('returns zero outstanding balance when overpaid', function () {
        $reservation = Reservation::factory()->create(['cost' => 30.00]);

        $reservation->transactions()->create([
            'transaction_id' => 'test_overpay_1',
            'type' => 'payment',
            'amount' => 40.00,
            'status' => 'completed',
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
        ]);

        $balance = PaymentService::getOutstandingBalance($reservation);

        expect($balance)->toBe(0.00);
    });

    it('can determine if reservation is fully paid', function () {
        $reservation = Reservation::factory()->create(['cost' => 45.00]);

        // Partially paid
        $reservation->transactions()->create([
            'transaction_id' => 'test_partial_1',
            'type' => 'payment',
            'amount' => 30.00,
            'status' => 'completed',
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
        ]);

        expect(PaymentService::isFullyPaid($reservation))->toBeFalse();

        // Fully paid
        $reservation->transactions()->create([
            'transaction_id' => 'test_partial_2',
            'type' => 'payment',
            'amount' => 15.00,
            'status' => 'completed',
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
        ]);

        expect(PaymentService::isFullyPaid($reservation))->toBeTrue();
    });

    it('considers overpaid reservation as fully paid', function () {
        $reservation = Reservation::factory()->create(['cost' => 30.00]);

        $reservation->transactions()->create([
            'transaction_id' => 'test_overpaid_1',
            'type' => 'payment',
            'amount' => 50.00,
            'status' => 'completed',
            'email' => 'test@example.com',
            'response' => ['test' => 'data'],
        ]);

        expect(PaymentService::isFullyPaid($reservation))->toBeTrue();
    });
});

describe('Stripe Integration Calculations', function () {
    it('can calculate processing fee', function () {
        // Test with $100 base amount
        // Expected: (100 * 0.029) + 0.30 = 2.90 + 0.30 = 3.20
        $fee = PaymentService::calculateProcessingFee(100.00);
        expect($fee)->toBe(3.20);

        // Test with $10 base amount
        // Expected: (10 * 0.029) + 0.30 = 0.29 + 0.30 = 0.59
        $fee = PaymentService::calculateProcessingFee(10.00);
        expect(round($fee, 2))->toBe(0.59);
    });

    it('can calculate total with fee coverage', function () {
        // For $100 base amount with fee coverage
        // Formula: (100 + 0.30) / (1 - 0.029) = 100.30 / 0.971 ≈ 103.30
        $total = PaymentService::calculateTotalWithFeeCoverage(100.00);
        expect(round($total, 2))->toBe(103.30);

        // For $10 base amount
        // Formula: (10 + 0.30) / (1 - 0.029) = 10.30 / 0.971 ≈ 10.61
        $total = PaymentService::calculateTotalWithFeeCoverage(10.00);
        expect(round($total, 2))->toBe(10.61);
    });

    it('can get fee breakdown without coverage', function () {
        $breakdown = PaymentService::getFeeBreakdown(50.00, false);

        expect($breakdown)->toBe([
            'base_amount' => 50.00,
            'fee_amount' => 0,
            'total_amount' => 50.00,
            'display_fee' => 0,
            'description' => '$50.00 membership',
        ]);
    });

    it('can get fee breakdown with coverage', function () {
        $breakdown = PaymentService::getFeeBreakdown(50.00, true);

        expect($breakdown)
            ->toHaveKey('base_amount', 50.00)
            ->toHaveKey('total_amount')
            ->toHaveKey('fee_amount')
            ->toHaveKey('display_fee')
            ->toHaveKey('description');

        // Verify the total is calculated correctly
        $expectedTotal = PaymentService::calculateTotalWithFeeCoverage(50.00);
        expect($breakdown['total_amount'])->toBe($expectedTotal);

        // Verify fee amount is the difference
        expect($breakdown['fee_amount'])->toBe($expectedTotal - 50.00);

        // Verify display fee is the simple calculation
        $expectedDisplayFee = PaymentService::calculateProcessingFee(50.00);
        expect($breakdown['display_fee'])->toBe($expectedDisplayFee);
    });

    it('can get fee display info', function () {
        $info = PaymentService::getFeeDisplayInfo(25.00);
        $expectedFee = PaymentService::calculateProcessingFee(25.00);
        $expectedTotal = PaymentService::calculateTotalWithFeeCoverage(25.00);

        expect($info)
            ->toHaveKey('display_fee', $expectedFee)
            ->toHaveKey('total_with_coverage', $expectedTotal)
            ->toHaveKey('message')
            ->toHaveKey('accurate_message');

        expect($info['message'])->toContain('Add $')
            ->and($info['message'])->toContain('to cover Stripe fees');

        expect($info['accurate_message'])->toContain('Covers processing fees');
    });

    it('can convert dollars to stripe amount', function () {
        expect(PaymentService::dollarsToStripeAmount(45.50))->toBe(4550);
        expect(PaymentService::dollarsToStripeAmount(100.00))->toBe(10000);
        expect(PaymentService::dollarsToStripeAmount(0.99))->toBe(99);
    });

    it('can convert stripe amount to dollars', function () {
        expect(PaymentService::stripeAmountToDollars(4550))->toBe(45.50);
        expect(PaymentService::stripeAmountToDollars(10000))->toBe(100.00);
        expect(PaymentService::stripeAmountToDollars(99))->toBe(0.99);
    });

    it('can calculate net amount after stripe fees', function () {
        // For $100 charged, expect to net: 100 - (100 * 0.029 + 0.30) = 100 - 3.20 = 96.80
        $net = PaymentService::calculateNetAmount(100.00);
        expect($net)->toBe(96.80);

        // For $10 charged, expect to net: 10 - (10 * 0.029 + 0.30) = 10 - 0.59 = 9.41
        $net = PaymentService::calculateNetAmount(10.00);
        expect($net)->toBe(9.41);
    });

    it('can validate fee coverage accuracy', function () {
        $baseAmount = 50.00;
        $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);

        // The coverage calculation should be accurate
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage))->toBeTrue();

        // Insufficient coverage should fail validation
        expect(PaymentService::validateFeeCoverage($baseAmount, $baseAmount))->toBeFalse();

        // Over-coverage should pass (within tolerance)
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage + 0.005))->toBeTrue();
    });

    it('validates fee coverage with tolerance for rounding', function () {
        $baseAmount = 33.33;
        $totalWithCoverage = PaymentService::calculateTotalWithFeeCoverage($baseAmount);

        // Should be valid within 1 cent tolerance
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage + 0.009))->toBeTrue();

        // Should fail outside tolerance
        expect(PaymentService::validateFeeCoverage($baseAmount, $totalWithCoverage - 0.02))->toBeFalse();
    });
});
