<?php

/**
 * Event → Space Reservation Sync Tests
 *
 * Tests the full lifecycle of how events at CMC venue automatically
 * sync with EventReservation records via EventObserver.
 */

use App\Actions\Events\SyncEventSpaceReservation;
use App\Facades\EventSyncService;
use App\Models\EventReservation;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Actions\CancelEvent;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Actions\RescheduleEvent;
use CorvMC\Events\Actions\UpdateEvent;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Facades\EventService;
use CorvMC\Events\Models\Venue;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Notification::fake();

    $this->cmcVenue = Venue::create([
        'name' => 'CMC Practice Space',
        'is_cmc' => true,
        'address' => '420 NW 5th St',
        'city' => 'Corvallis',
        'state' => 'OR',
    ]);

    $this->externalVenue = Venue::create([
        'name' => 'External Venue',
        'is_cmc' => false,
        'address' => '123 Main St',
        'city' => 'Portland',
        'state' => 'OR',
    ]);

    $this->organizer = User::factory()->create();
    $this->organizer->assignRole('member');
});

describe('Event Creation', function () {
    it('creates EventReservation when event is created at CMC venue', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        expect($event->spaceReservation)->not->toBeNull()
            ->and($event->spaceReservation)->toBeInstanceOf(EventReservation::class);
    });

    it('includes setup time (2 hours before) and breakdown time (1 hour after)', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3); // 19:00 - 22:00

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $reservation = $event->spaceReservation;
        expect($reservation->reserved_at->hour)->toBe(17) // 2 hours before 19:00
            ->and($reservation->reserved_until->hour)->toBe(23); // 1 hour after 22:00
    });

    it('does not create EventReservation for external venue events', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'External Concert',
            'description' => 'An external event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->externalVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        expect($event->spaceReservation)->toBeNull();
    });

    it('does not create duplicate reservations when SyncEventSpaceReservation is called multiple times', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Simulate Filament page calling SyncEventSpaceReservation explicitly
        EventSyncService::syncSpaceReservation($event, 120, 60, force: false);
        EventSyncService::syncSpaceReservation($event, 120, 60, force: true);

        $reservationCount = EventReservation::where('reservable_type', 'event')
            ->where('reservable_id', $event->id)
            ->count();

        expect($reservationCount)->toBe(1);
    });
});

describe('Event Time Changes', function () {
    it('updates EventReservation when event time changes', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $newStartTime = $startTime->copy()->addHours(2); // Move to 21:00
        $newEndTime = $newStartTime->copy()->addHours(3); // 21:00 - 00:00

        EventService::update($event, [
            'start_datetime' => $newStartTime,
            'end_datetime' => $newEndTime,
        ]);

        $event->refresh();
        expect($event->spaceReservation->reserved_at->hour)->toBe(19) // 2 hours before 21:00
            ->and($event->spaceReservation->reserved_until->hour)->toBe(1); // 1 hour after 00:00
    });
});

describe('Event Cancellation', function () {
    it('cancels EventReservation when event is cancelled', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $reservation = $event->spaceReservation;
        expect($reservation->status)->toBeInstanceOf(Confirmed::class);

        EventService::cancel($event, 'Test cancellation');

        $reservation->refresh();
        expect($reservation->status)->toBeInstanceOf(Cancelled::class)
            ->and($reservation->cancellation_reason)->toBe('Event was cancelled');
    });

    it('cancels EventReservation when event is postponed', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $reservation = $event->spaceReservation;

        $event->update(['status' => EventStatus::Postponed]);

        $reservation->refresh();
        expect($reservation->status)->toBeInstanceOf(Cancelled::class)
            ->and($reservation->cancellation_reason)->toBe('Event was postponed');
    });
});

describe('Event Restoration', function () {
    it('creates a new EventReservation when cancelled event is reactivated', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $originalReservation = $event->spaceReservation;
        $originalId = $originalReservation->id;

        EventService::cancel($event, 'Test cancellation');
        $originalReservation->refresh();
        expect($originalReservation->status)->toBeInstanceOf(Cancelled::class);

        $event->refresh();
        $event->update(['status' => EventStatus::Scheduled]);

        $event->refresh();
        // Old reservation stays cancelled, a new one is created
        $originalReservation->refresh();
        expect($originalReservation->status)->toBeInstanceOf(Cancelled::class);

        expect($event->spaceReservation)->not->toBeNull()
            ->and($event->spaceReservation->id)->not->toBe($originalId)
            ->and($event->spaceReservation->status)->toBeInstanceOf(Confirmed::class);
    });

    it('recreates EventReservation when soft-deleted event is restored', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $originalReservationId = $event->spaceReservation->id;

        // Soft delete the event (this hard-deletes the reservation)
        $event->delete();
        expect(EventReservation::find($originalReservationId))->toBeNull();

        // Restore the event
        $event->restore();
        $event->refresh();

        // A new reservation should be created
        expect($event->spaceReservation)->not->toBeNull()
            ->and($event->spaceReservation->status)->toBeInstanceOf(Confirmed::class);
    });
});

describe('Event Rescheduling', function () {
    it('creates new reservation and cancels old one when event is rescheduled', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $originalEvent = EventService::create([
            'title' => 'Original Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $originalEvent->refresh();
        $originalReservation = $originalEvent->spaceReservation;
        expect($originalReservation->status)->toBeInstanceOf(Confirmed::class);

        $newStartTime = Carbon::now()->addDays(20)->setHour(20)->setMinute(0)->setSecond(0);
        $newEndTime = $newStartTime->copy()->addHours(3);
        $newEvent = EventService::reschedule($originalEvent, $newStartTime, $newEndTime);

        $originalReservation->refresh();
        expect($originalReservation->status)->toBeInstanceOf(Cancelled::class);

        $newEvent->refresh();
        expect($newEvent->spaceReservation)->not->toBeNull()
            ->and($newEvent->spaceReservation->id)->not->toBe($originalReservation->id)
            ->and($newEvent->spaceReservation->status)->toBeInstanceOf(Confirmed::class);
    });

    it('cancels reservation when event is rescheduled to TBA', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $reservation = $event->spaceReservation;

        EventService::reschedule($event, null, null);

        $reservation->refresh();
        expect($reservation->status)->toBeInstanceOf(Cancelled::class)
            ->and($reservation->cancellation_reason)->toBe('Event was postponed');
    });
});

describe('Event Deletion', function () {
    it('deletes EventReservation when event is deleted', function () {
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = EventService::create([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $event->refresh();
        $reservationId = $event->spaceReservation->id;
        expect(EventReservation::find($reservationId))->not->toBeNull();

        $event->delete();

        expect(EventReservation::find($reservationId))->toBeNull();
    });
});
