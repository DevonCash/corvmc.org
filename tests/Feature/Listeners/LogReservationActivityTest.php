<?php

use App\Models\User;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

it('logs activity when a reservation is created', function () {
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->confirmed()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
    ]);

    Activity::query()->delete();

    ReservationCreated::dispatch($reservation);

    $activity = Activity::where('event', 'created')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Reservation created for')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($reservation->id)
        ->and($activity->properties)->toHaveKey('reserved_at')
        ->and($activity->properties)->toHaveKey('hours_used');
});

it('logs activity when a reservation is confirmed by a user', function () {
    $user = User::factory()->create();
    $manager = User::factory()->withRole('practice space manager')->create();
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    Activity::query()->delete();

    $this->actingAs($manager);
    ReservationConfirmed::dispatch($reservation, ReservationStatus::Scheduled);

    $activity = Activity::where('event', 'confirmed')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Reservation confirmed')
        ->and($activity->causer_id)->toBe($manager->id)
        ->and($activity->properties['previous_status'])->toBe('pending');
});

it('logs auto-confirmed when no user is authenticated', function () {
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    Activity::query()->delete();

    auth()->logout();
    ReservationConfirmed::dispatch($reservation, ReservationStatus::Scheduled);

    $activity = Activity::where('event', 'confirmed')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Reservation auto-confirmed')
        ->and($activity->causer_id)->toBeNull();
});

it('logs activity when a reservation is cancelled with reason', function () {
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'status' => ReservationStatus::Cancelled,
        'cancellation_reason' => 'Schedule conflict',
    ]);

    Activity::query()->delete();

    $this->actingAs($user);
    ReservationCancelled::dispatch($reservation, ReservationStatus::Confirmed);

    $activity = Activity::where('event', 'cancelled')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Reservation cancelled: Schedule conflict')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['original_status'])->toBe('confirmed')
        ->and($activity->properties['cancellation_reason'])->toBe('Schedule conflict');
});

it('logs activity when a reservation is rescheduled', function () {
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->confirmed()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
    ]);

    Activity::query()->delete();

    $this->actingAs($user);
    ReservationUpdated::dispatch($reservation, 2.0);

    $activity = Activity::where('event', 'rescheduled')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Reservation rescheduled to')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['old_billable_units'])->toBe(2);
});

it('logs activity when a reservation is marked as paid', function () {
    $manager = User::factory()->withRole('practice space manager')->create();
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->confirmed()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
    ]);

    Activity::query()->delete();

    $this->actingAs($manager);
    \CorvMC\Finance\Actions\Payments\MarkReservationAsPaid::run($reservation, 'cash', 'Paid at front desk');

    $activity = Activity::where('event', 'payment_recorded')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Payment recorded via cash')
        ->and($activity->causer_id)->toBe($manager->id)
        ->and($activity->properties['payment_method'])->toBe('cash');
});

it('logs activity when a reservation is comped', function () {
    $manager = User::factory()->withRole('practice space manager')->create();
    $user = User::factory()->create();
    $reservation = RehearsalReservation::factory()->confirmed()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
    ]);

    Activity::query()->delete();

    $this->actingAs($manager);
    \CorvMC\Finance\Actions\Payments\MarkReservationAsComped::run($reservation, 'Community event volunteer');

    $activity = Activity::where('event', 'comped')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Reservation comped: Community event volunteer')
        ->and($activity->causer_id)->toBe($manager->id)
        ->and($activity->properties['reason'])->toBe('Community event volunteer');
});

it('logs activity when a reservation is auto-cancelled', function () {
    $user = User::factory()->create();
    RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'status' => ReservationStatus::Reserved,
        'reserved_at' => now()->addDays(2),
        'reserved_until' => now()->addDays(2)->addHours(2),
        'hours_used' => 2,
    ]);

    Activity::query()->delete();

    \CorvMC\SpaceManagement\Actions\Reservations\AutoCancelUnconfirmedReservations::run();

    $activity = Activity::where('event', 'auto_cancelled')
        ->where('log_name', 'reservation')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('auto-cancelled')
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->properties['original_status'])->toBe('reserved');
});
