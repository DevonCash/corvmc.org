<?php

use App\Actions\Notifications\SendReservationReminders;
use App\Models\User;
use Brick\Money\Money;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Notifications\ReservationReminderNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

it('sends reminder notification without error when reservation has a charge', function () {
    Notification::fake();

    $user = User::factory()->create();
    $tomorrow = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0);

    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'reserved_at' => $tomorrow,
        'reserved_until' => $tomorrow->copy()->addHours(2),
        'status' => ReservationStatus::Confirmed,
    ]);

    Charge::createForChargeable($reservation, 3000, 3000);

    SendReservationReminders::run();

    Notification::assertSentTo($user, ReservationReminderNotification::class);
});

it('sends reminder notification without error when reservation has no charge', function () {
    Notification::fake();

    $user = User::factory()->create();
    $tomorrow = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0);

    RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'reserved_at' => $tomorrow,
        'reserved_until' => $tomorrow->copy()->addHours(2),
        'status' => ReservationStatus::Confirmed,
    ]);

    SendReservationReminders::run();

    Notification::assertSentTo($user, ReservationReminderNotification::class);
});

it('includes correct cost in database notification data', function () {
    $user = User::factory()->create();
    $tomorrow = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0);

    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'reserved_at' => $tomorrow,
        'reserved_until' => $tomorrow->copy()->addHours(2),
        'status' => ReservationStatus::Confirmed,
    ]);

    Charge::createForChargeable($reservation, 3000, 3000);

    $notification = new ReservationReminderNotification($reservation);
    $data = $notification->toDatabase($user);

    expect($data['cost'])->toBe(3000);
});

it('returns zero cost in database notification when no charge exists', function () {
    $user = User::factory()->create();
    $tomorrow = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0);

    $reservation = RehearsalReservation::factory()->create([
        'reservable_type' => 'user',
        'reservable_id' => $user->id,
        'reserved_at' => $tomorrow,
        'reserved_until' => $tomorrow->copy()->addHours(2),
        'status' => ReservationStatus::Confirmed,
    ]);

    $notification = new ReservationReminderNotification($reservation);
    $data = $notification->toDatabase($user);

    expect($data['cost'])->toBe(0);
});
