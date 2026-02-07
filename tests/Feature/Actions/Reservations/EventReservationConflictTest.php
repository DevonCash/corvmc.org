<?php

/**
 * Test that rehearsal reservations conflict with event reservations.
 *
 * This is a regression test for a bug where rehearsal reservations
 * could be created that intersected with event reservations.
 */

use App\Models\EventReservation;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\SpaceManagement\Actions\Reservations\CheckTimeSlotAvailability;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\SpaceManagement\Actions\Reservations\GetConflictingReservations;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    // Create CMC venue
    $this->venue = Venue::create([
        'name' => 'CMC Practice Space',
        'is_cmc' => true,
        'address' => '420 NW 5th St',
        'city' => 'Corvallis',
        'state' => 'OR',
    ]);
});

it('detects event reservations as conflicts when creating rehearsal reservations', function () {
    // Arrange: Create an event with a space reservation
    $organizer = User::factory()->create();

    $eventStart = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
    $eventEnd = $eventStart->copy()->addHours(3);

    $event = Event::create([
        'title' => 'Test Concert',
        'description' => 'A test event',
        'start_datetime' => $eventStart,
        'end_datetime' => $eventEnd,
        'venue_id' => $this->venue->id,
        'organizer_id' => $organizer->id,
        'status' => EventStatus::Scheduled,
    ]);

    // Create the event reservation manually (simulating what SyncEventSpaceReservation does)
    // Use 30 min setup/teardown buffer
    $reservedAt = $eventStart->copy()->subMinutes(30);
    $reservedUntil = $eventEnd->copy()->addMinutes(30);

    $eventReservation = EventReservation::create([
        'type' => 'event_reservation',
        'reservable_type' => 'event',
        'reservable_id' => $event->id,
        'reserved_at' => $reservedAt,
        'reserved_until' => $reservedUntil,
        'status' => ReservationStatus::Confirmed,
        'hours_used' => $reservedAt->diffInHours($reservedUntil),
    ]);

    // Verify the event reservation was created properly
    expect($eventReservation->exists)->toBeTrue();
    expect($eventReservation->type)->toBe('event_reservation');

    // Act: Check if the time slot is available for a rehearsal
    $isAvailable = CheckTimeSlotAvailability::run($eventStart, $eventEnd);

    // Assert: Time slot should NOT be available
    expect($isAvailable)->toBeFalse('Event reservation should block the time slot');
});

it('GetConflictingReservations finds event reservations', function () {
    // Arrange: Create event reservation directly
    $organizer = User::factory()->create();

    $eventStart = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
    $eventEnd = $eventStart->copy()->addHours(3);

    $event = Event::create([
        'title' => 'Test Concert',
        'start_datetime' => $eventStart,
        'end_datetime' => $eventEnd,
        'venue_id' => $this->venue->id,
        'organizer_id' => $organizer->id,
        'status' => EventStatus::Scheduled,
    ]);

    $eventReservation = EventReservation::create([
        'type' => 'event_reservation',
        'reservable_type' => 'event',
        'reservable_id' => $event->id,
        'reserved_at' => $eventStart,
        'reserved_until' => $eventEnd,
        'status' => ReservationStatus::Confirmed,
        'hours_used' => 3,
    ]);

    // Act: Get conflicting reservations
    $conflicts = GetConflictingReservations::run($eventStart, $eventEnd);

    // Assert: Should find the event reservation
    expect($conflicts)->not->toBeEmpty()
        ->and($conflicts->first())->toBeInstanceOf(EventReservation::class);
});

it('GetAllConflicts includes event reservations in results', function () {
    // Arrange: Create event reservation
    $organizer = User::factory()->create();

    $eventStart = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
    $eventEnd = $eventStart->copy()->addHours(3);

    $event = Event::create([
        'title' => 'Test Concert',
        'start_datetime' => $eventStart,
        'end_datetime' => $eventEnd,
        'venue_id' => $this->venue->id,
        'organizer_id' => $organizer->id,
        'status' => EventStatus::Scheduled,
    ]);

    EventReservation::create([
        'type' => 'event_reservation',
        'reservable_type' => 'event',
        'reservable_id' => $event->id,
        'reserved_at' => $eventStart,
        'reserved_until' => $eventEnd,
        'status' => ReservationStatus::Confirmed,
        'hours_used' => 3,
    ]);

    // Act: Get all conflicts
    $conflicts = GetAllConflicts::run($eventStart, $eventEnd);

    // Assert: Event reservation should appear somewhere
    $hasEventReservation = $conflicts['reservations']->isNotEmpty() || $conflicts['productions']->isNotEmpty();
    expect($hasEventReservation)->toBeTrue('Event reservation should be detected as a conflict');
});

it('prevents creating rehearsal reservation that overlaps with event reservation', function () {
    // Arrange: Create event with space reservation
    $organizer = User::factory()->create();

    $eventStart = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
    $eventEnd = $eventStart->copy()->addHours(3);

    $event = Event::create([
        'title' => 'Test Concert',
        'start_datetime' => $eventStart,
        'end_datetime' => $eventEnd,
        'venue_id' => $this->venue->id,
        'organizer_id' => $organizer->id,
        'status' => EventStatus::Scheduled,
    ]);

    EventReservation::create([
        'type' => 'event_reservation',
        'reservable_type' => 'event',
        'reservable_id' => $event->id,
        'reserved_at' => $eventStart,
        'reserved_until' => $eventEnd,
        'status' => ReservationStatus::Confirmed,
        'hours_used' => 3,
    ]);

    // Act: Try to create overlapping rehearsal reservation
    $user = User::factory()->create();
    $user->assignRole('member');

    // Assert: Should throw validation error
    expect(fn () => CreateReservation::run($user, $eventStart, $eventEnd))
        ->toThrow(\InvalidArgumentException::class);
});
