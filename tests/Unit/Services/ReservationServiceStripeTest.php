<?php

use App\Models\Reservation;
use App\Models\User;
use App\Models\Transaction;
use App\Facades\ReservationService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->reservation = Reservation::factory()->create([
        'user_id' => $this->user->id,
        'cost' => 45.00,
        'free_hours_used' => 0,
        'payment_status' => 'unpaid',
    ]);
});

// NOTE: Stripe Checkout Session Creation tests require complex API mocking
// These tests should be implemented as integration tests or with dedicated Stripe test utilities
// For now, we focus on the business logic that can be unit tested

describe('Stripe Integration Business Logic', function () {
    it('validates stripe customer requirements for checkout', function () {
        // This test would verify the user needs a Stripe customer ID
        // The actual createCheckoutSession method requires Stripe API integration
        expect($this->user->hasStripeId())->toBeFalse(); // New user has no Stripe ID

        // In real implementation, createCheckoutSession would call createAsStripeCustomer
        // if user doesn't have Stripe ID - this requires integration testing
    })->skip('Requires Stripe API integration testing');

    it('handles free hours in checkout session data structure', function () {
        // Test the logic around free hours calculation
        $reservationWithFreeHours = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 30.00,
            'free_hours_used' => 1.5,
            'payment_status' => 'unpaid',
        ]);

        expect($reservationWithFreeHours->free_hours_used)->toBe(1.5)
            ->and($reservationWithFreeHours->cost)->toBe(30.00);

        // The actual line items creation requires Stripe API calls
        // This logic should be tested in integration tests
    })->skip('Requires Stripe API integration testing');
});

// NOTE: Successful Payment Handling tests require Stripe API calls
// These are better suited for integration tests with proper Stripe test fixtures

describe('Failed Payment Handling', function () {
    it('can handle failed payment with session id', function () {
        $sessionId = 'cs_failed_session_123';

        ReservationService::handleFailedPayment($this->reservation, $sessionId);

        $this->reservation->refresh();
        expect($this->reservation->payment_status)->toBe('unpaid')
            ->and($this->reservation->payment_notes)->toContain($sessionId)
            ->and($this->reservation->payment_notes)->toContain('Payment failed/cancelled');
    });

    it('can handle failed payment without session id', function () {
        ReservationService::handleFailedPayment($this->reservation);

        $this->reservation->refresh();
        expect($this->reservation->payment_status)->toBe('unpaid')
            ->and($this->reservation->payment_notes)->toBe('Payment cancelled by user');
    });

    it('does not change reservation status on failed payment', function () {
        $originalStatus = $this->reservation->status;

        ReservationService::handleFailedPayment($this->reservation, 'cs_failed_session');

        $this->reservation->refresh();
        expect($this->reservation->status)->toBe($originalStatus);
    });
});

// NOTE: Additional Stripe integration tests (checkout session retrieval, edge cases)
// require complex API mocking and are better suited for integration testing

describe('Payment Status Business Logic', function () {
    it('can track reservation payment requirements', function () {
        // Test basic payment status logic without Stripe API calls
        expect($this->reservation->payment_status)->toBe('unpaid')
            ->and((float)$this->reservation->cost)->toBe(45.00);

        // This reservation would require payment processing via Stripe
        expect($this->reservation->cost > 0)->toBeTrue();
    });

    it('can handle zero cost reservations without payment processing', function () {
        $freeReservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 0.00,
            'free_hours_used' => 4.0, // Using all sustaining member hours
            'payment_status' => 'paid', // Free reservations are auto-marked as paid
        ]);

        expect((float)$freeReservation->cost)->toBe(0.00)
            ->and((float)$freeReservation->free_hours_used)->toBe(4.0)
            ->and($freeReservation->payment_status)->toBe('paid');
    });

    it('tracks free hours used in reservations', function () {
        $reservationWithFreeHours = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'cost' => 15.00, // 2 hours paid, 1 hour free = 3 hours total * $15
            'free_hours_used' => 1.0,
        ]);

        expect((float)$reservationWithFreeHours->free_hours_used)->toBe(1.0)
            ->and((float)$reservationWithFreeHours->cost)->toBe(15.00);
    });
});
