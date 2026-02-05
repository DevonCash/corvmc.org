<?php

use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

it('creates a reservation attributed to the target member, not the manager', function () {
    $manager = User::factory()->withRole('practice space manager')->create();
    $member = User::factory()->create();

    $this->actingAs($manager);

    $startTime = Carbon::parse('tomorrow 10:00', config('app.timezone'));
    $endTime = Carbon::parse('tomorrow 12:00', config('app.timezone'));

    $reservation = CreateReservation::run($member, $startTime, $endTime);

    // The reservation owner is determined by the polymorphic reservable relationship
    expect($reservation->reservable_id)->toBe($member->id)
        ->and($reservation->reservable_type)->toBe('user')
        ->and($reservation->reservable_id)->not->toBe($manager->id);
});

it('calculates cost based on the target member, not the authenticated user', function () {
    $manager = User::factory()->withRole('practice space manager')->create();
    $sustainingMember = User::factory()->sustainingMember()->create();
    $regularMember = User::factory()->create();

    $this->actingAs($manager);

    $startTime = Carbon::parse('tomorrow 10:00', config('app.timezone'));
    $endTime = Carbon::parse('tomorrow 12:00', config('app.timezone'));

    $sustainingCost = CalculateReservationCost::run($sustainingMember, $startTime, $endTime);
    $regularCost = CalculateReservationCost::run($regularMember, $startTime, $endTime);

    // Sustaining member should get free hours
    expect($sustainingCost['is_sustaining_member'])->toBeTrue()
        ->and($sustainingCost['free_hours'])->toBeGreaterThan(0);

    // Regular member should pay full price
    expect($regularCost['is_sustaining_member'])->toBeFalse()
        ->and($regularCost['free_hours'])->toBe(0)
        ->and($regularCost['paid_hours'])->toBe(2.0);
});

it('creates a reservation for a sustaining member with free hours applied', function () {
    $manager = User::factory()->withRole('practice space manager')->create();
    $sustainingMember = User::factory()->sustainingMember()->create();

    $this->actingAs($manager);

    $startTime = Carbon::parse('tomorrow 10:00', config('app.timezone'));
    $endTime = Carbon::parse('tomorrow 12:00', config('app.timezone'));

    $reservation = CreateReservation::run($sustainingMember, $startTime, $endTime);

    // The reservation owner is determined by the polymorphic reservable relationship
    expect($reservation->reservable_id)->toBe($sustainingMember->id)
        ->and($reservation->reservable_type)->toBe('user');

    // Verify cost was calculated for the sustaining member
    $cost = CalculateReservationCost::run($sustainingMember, $startTime, $endTime);
    expect($cost['free_hours'])->toBeGreaterThan(0)
        ->and($cost['is_sustaining_member'])->toBeTrue();
});
