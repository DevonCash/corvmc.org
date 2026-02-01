<?php

use Brick\Money\Money;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Actions\Reservations\HandleSuccessfulPayment;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

it('updates payment status to paid for unpaid reservation', function () {
    $user = User::factory()->create();

    $reservation = RehearsalReservation::factory()
        ->pending()
        ->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
        ]);

    // Create a pending charge for the reservation
    Charge::create([
        'user_id' => $user->id,
        'chargeable_type' => $reservation->getMorphClass(),
        'chargeable_id' => $reservation->id,
        'amount' => Money::ofMinor(3000, 'USD'),
        'net_amount' => Money::ofMinor(3000, 'USD'),
        'status' => ChargeStatus::Pending,
    ]);

    expect($reservation->getChargeStatus())->toBe(ChargeStatus::Pending);

    HandleSuccessfulPayment::run($reservation, 'cs_test_session_123');

    $reservation->refresh();

    expect($reservation->getChargeStatus())->toBe(ChargeStatus::Paid)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->charge->payment_method)->toBe('stripe')
        ->and($reservation->charge->paid_at)->not->toBeNull();
});

it('is idempotent - skips if already paid', function () {
    $user = User::factory()->create();
    $paidAt = now()->subHour();

    $reservation = RehearsalReservation::factory()
        ->confirmed()
        ->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
        ]);

    // Create a paid charge for the reservation
    Charge::create([
        'user_id' => $user->id,
        'chargeable_type' => $reservation->getMorphClass(),
        'chargeable_id' => $reservation->id,
        'amount' => Money::ofMinor(3000, 'USD'),
        'net_amount' => Money::ofMinor(3000, 'USD'),
        'status' => ChargeStatus::Paid,
        'payment_method' => 'stripe',
        'paid_at' => $paidAt,
        'notes' => 'Original payment',
    ]);

    HandleSuccessfulPayment::run($reservation, 'cs_test_different_session');

    $reservation->refresh();

    // Should not have changed
    expect($reservation->charge->notes)->toBe('Original payment')
        ->and($reservation->charge->paid_at->timestamp)->toBe($paidAt->timestamp);
});
