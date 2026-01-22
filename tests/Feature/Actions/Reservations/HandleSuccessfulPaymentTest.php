<?php

use CorvMC\SpaceManagement\Actions\Reservations\HandleSuccessfulPayment;
use App\Enums\ReservationStatus;
use App\Models\RehearsalReservation;

it('updates payment status to paid for unpaid reservation', function () {
    $reservation = RehearsalReservation::factory()
        ->pending()
        ->create([
            'cost' => 3000, // $30.00
            'payment_status' => 'unpaid',
        ]);

    expect($reservation->payment_status)->toBe('unpaid');

    HandleSuccessfulPayment::run($reservation, 'cs_test_session_123');

    $reservation->refresh();

    expect($reservation->payment_status)->toBe('paid')
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->payment_method)->toBe('stripe')
        ->and($reservation->paid_at)->not->toBeNull();
});

it('is idempotent - skips if already paid', function () {
    $paidAt = now()->subHour();

    $reservation = RehearsalReservation::factory()
        ->confirmed()
        ->create([
            'cost' => 3000,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'paid_at' => $paidAt,
            'payment_notes' => 'Original payment',
        ]);

    HandleSuccessfulPayment::run($reservation, 'cs_test_different_session');

    $reservation->refresh();

    // Should not have changed
    expect($reservation->payment_notes)->toBe('Original payment')
        ->and($reservation->paid_at->timestamp)->toBe($paidAt->timestamp);
});
