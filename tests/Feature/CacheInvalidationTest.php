<?php

/**
 * Cache Invalidation Tests
 *
 * These tests verify that observers correctly clear caches when
 * models are created, updated, or deleted.
 */

use App\Models\User;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Reservation Cache Invalidation
|--------------------------------------------------------------------------
*/

describe('Reservation Cache Invalidation', function () {
    beforeEach(function () {
        Notification::fake();

        $this->user = User::factory()->create();
        $this->user->assignRole('member');

        Venue::create([
            'name' => 'CMC Practice Space',
            'is_cmc' => true,
            'address' => '420 NW 5th St',
            'city' => 'Corvallis',
            'state' => 'OR',
        ]);
    });

    it('clears conflict cache when reservation is created', function () {
        // Arrange: Set up a cache key that should be cleared
        $date = Carbon::now()->addDays(5)->format('Y-m-d');
        Cache::put("reservations.conflicts.{$date}", 'cached_value', 3600);

        // Act: Create a reservation on that date
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $this->user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Reserved,
        ]);

        // Assert: Cache was cleared
        expect(Cache::has("reservations.conflicts.{$date}"))->toBeFalse();
    });

    it('clears user stats cache when reservation changes', function () {
        // Arrange: Create reservation and set up cache
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $this->user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Reserved,
        ]);

        Cache::put("user_stats.{$this->user->id}", 'cached_value', 3600);

        // Act: Update the reservation
        $reservation->update(['status' => ReservationStatus::Confirmed]);

        // Assert: User stats cache was cleared
        expect(Cache::has("user_stats.{$this->user->id}"))->toBeFalse();
    });

    it('clears old date cache when reservation date is moved', function () {
        // Arrange: Create reservation
        $originalDate = Carbon::now()->addDays(5);
        $newDate = Carbon::now()->addDays(7);

        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $this->user->id,
            'reserved_at' => $originalDate->copy()->setHour(14),
            'reserved_until' => $originalDate->copy()->setHour(16),
            'status' => ReservationStatus::Reserved,
        ]);

        // Set up cache for both dates
        Cache::put("reservations.conflicts.{$originalDate->format('Y-m-d')}", 'cached_value', 3600);
        Cache::put("reservations.conflicts.{$newDate->format('Y-m-d')}", 'cached_value', 3600);

        // Act: Move reservation to new date
        $reservation->update([
            'reserved_at' => $newDate->copy()->setHour(14),
            'reserved_until' => $newDate->copy()->setHour(16),
        ]);

        // Assert: Both old and new date caches were cleared
        expect(Cache::has("reservations.conflicts.{$originalDate->format('Y-m-d')}"))->toBeFalse()
            ->and(Cache::has("reservations.conflicts.{$newDate->format('Y-m-d')}"))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Event Cache Invalidation
|--------------------------------------------------------------------------
*/

describe('Event Cache Invalidation', function () {
    beforeEach(function () {
        Notification::fake();

        $this->cmcVenue = Venue::create([
            'name' => 'CMC Practice Space',
            'is_cmc' => true,
            'address' => '420 NW 5th St',
            'city' => 'Corvallis',
            'state' => 'OR',
        ]);

        $this->organizer = User::factory()->create();
        $this->organizer->assignRole('member');
    });

    it('clears conflict cache for event date', function () {
        // Arrange: Set up cache for the event date
        $eventDate = Carbon::now()->addDays(10);
        Cache::put("events.conflicts.{$eventDate->format('Y-m-d')}", 'cached_value', 3600);

        // Act: Create event
        $startTime = $eventDate->copy()->setHour(19)->setMinute(0)->setSecond(0);

        CreateEvent::run([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $startTime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Assert: Cache was cleared
        expect(Cache::has("events.conflicts.{$eventDate->format('Y-m-d')}"))->toBeFalse();
    });

    it('clears organizer user stats cache', function () {
        // Arrange: Set up cache for organizer stats
        Cache::put("user_stats.{$this->organizer->id}", 'cached_value', 3600);

        // Act: Create event
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);

        CreateEvent::run([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $startTime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Assert: Organizer stats cache was cleared
        expect(Cache::has("user_stats.{$this->organizer->id}"))->toBeFalse();
    });

    it('clears old date cache when event is moved', function () {
        // Arrange: Create event
        $originalDate = Carbon::now()->addDays(10);
        $newDate = Carbon::now()->addDays(14);

        $startTime = $originalDate->copy()->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $startTime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Set up caches for both dates
        Cache::put("events.conflicts.{$originalDate->format('Y-m-d')}", 'cached_value', 3600);
        Cache::put("events.conflicts.{$newDate->format('Y-m-d')}", 'cached_value', 3600);

        // Act: Move event to new date
        $newStartTime = $newDate->copy()->setHour(19)->setMinute(0)->setSecond(0);
        $event->update([
            'start_datetime' => $newStartTime,
            'end_datetime' => $newStartTime->copy()->addHours(3),
        ]);

        // Assert: Both date caches were cleared
        expect(Cache::has("events.conflicts.{$originalDate->format('Y-m-d')}"))->toBeFalse()
            ->and(Cache::has("events.conflicts.{$newDate->format('Y-m-d')}"))->toBeFalse();
    });

    it('clears caches when event is deleted', function () {
        // Arrange: Create event
        $eventDate = Carbon::now()->addDays(10);
        $startTime = $eventDate->copy()->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $startTime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Set up cache
        Cache::put("events.conflicts.{$eventDate->format('Y-m-d')}", 'cached_value', 3600);

        // Act: Delete event
        $event->delete();

        // Assert: Cache was cleared
        expect(Cache::has("events.conflicts.{$eventDate->format('Y-m-d')}"))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| User Cache Invalidation
|--------------------------------------------------------------------------
*/

describe('User Cache Invalidation', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('clears user-specific caches when user is updated', function () {
        // Arrange: Create user and set up caches
        $user = User::factory()->create();
        $user->assignRole('member');

        Cache::put("user_stats.{$user->id}", ['stat' => 'value'], 3600);
        Cache::put("user_activity.{$user->id}", ['activity' => 'data'], 3600);

        // Act: Update user
        $user->update(['name' => 'Updated Name']);

        // Assert: User-specific caches were cleared
        expect(Cache::has("user_stats.{$user->id}"))->toBeFalse()
            ->and(Cache::has("user_activity.{$user->id}"))->toBeFalse();
    });

    it('clears sustaining member caches when role changes', function () {
        // Arrange: Create user and set up caches
        $user = User::factory()->create();
        $user->assignRole('member');

        Cache::put('sustaining_members', ['list' => 'of members'], 3600);
        Cache::put('subscription_stats', ['stats' => 'data'], 3600);

        // Act: Update user
        $user->update(['email' => 'newemail@example.com']);

        // Assert: Global user-related caches were cleared
        expect(Cache::has('sustaining_members'))->toBeFalse()
            ->and(Cache::has('subscription_stats'))->toBeFalse();
    });

    it('clears caches when user is deleted', function () {
        // Arrange: Create user and set up caches
        $user = User::factory()->create();
        $userId = $user->id;

        Cache::put("user_stats.{$userId}", ['stat' => 'value'], 3600);
        Cache::put("user_activity.{$userId}", ['activity' => 'data'], 3600);
        Cache::put('sustaining_members', ['list' => 'of members'], 3600);

        // Act: Delete user
        $user->delete();

        // Assert: All caches were cleared
        expect(Cache::has("user_stats.{$userId}"))->toBeFalse()
            ->and(Cache::has("user_activity.{$userId}"))->toBeFalse()
            ->and(Cache::has('sustaining_members'))->toBeFalse();
    });
});
