<?php

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use CorvMC\SpaceManagement\States\ReservationState\Reserved;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use CorvMC\SpaceManagement\States\ReservationState\Completed;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

test('reservation can transition from scheduled to confirmed', function () {
    $reservation = new RehearsalReservation();
    $reservation->status = Scheduled::class;

    expect($reservation->status->canTransitionTo(Confirmed::class))->toBeTrue();
    expect($reservation->status->canTransitionTo(Completed::class))->toBeFalse();
});

test('reservation can transition from reserved to confirmed', function () {
    $reservation = new RehearsalReservation();
    $reservation->status = Reserved::class;

    expect($reservation->status->canTransitionTo(Confirmed::class))->toBeTrue();
    expect($reservation->status->canTransitionTo(Scheduled::class))->toBeFalse();
});

test('reservation cannot transition from cancelled to confirmed', function () {
    $reservation = new RehearsalReservation();
    $reservation->status = Cancelled::class;

    expect($reservation->status->canTransitionTo(Confirmed::class))->toBeFalse();
    expect($reservation->status->isActive())->toBeFalse();
});

test('state transition updates status', function () {
    $reservation = RehearsalReservation::factory()->make([
        'status' => Scheduled::class,
    ]);
    $reservation->forceSave();
    
    // Debug: check state before and after transition
    expect($reservation->status)->toBeInstanceOf(Scheduled::class);
    
    $reservation->status->transitionTo(Confirmed::class);
    
    // Check if the state changed on the model itself
    expect($reservation->status)->toBeInstanceOf(Confirmed::class);
    
    $reservation->forceSave();

    expect($reservation->fresh()->status)->toBeInstanceOf(Confirmed::class);
    // TODO: Test confirmed_at once column is added
    // expect($reservation->fresh()->confirmed_at)->not->toBeNull();
});

test('invalid state transition throws exception', function () {
    $reservation = RehearsalReservation::factory()->make([
        'status' => Cancelled::class,
    ]);
    $reservation->forceSave();

    $reservation->status->transitionTo(Confirmed::class);
})->throws(CouldNotPerformTransition::class);

test('state has correct properties', function () {
    $scheduled = new Scheduled(new RehearsalReservation());

    expect($scheduled->label())->toBe('Scheduled');
    expect($scheduled->color())->toBe('info');
    expect($scheduled->icon())->toBe('tabler-calendar-event');
    expect($scheduled->requiresConfirmation())->toBeTrue();
});
