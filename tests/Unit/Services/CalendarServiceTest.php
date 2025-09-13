<?php

use App\Facades\CalendarService;
use Carbon\Carbon;
use Guava\Calendar\ValueObjects\CalendarEvent;

beforeEach(function () {
    $this->user = $this->createUser();
    $this->otherUser = $this->createUser();
});

describe('reservationToCalendarEvent', function () {
    it('shows full details for own reservation', function () {
        $this->actingAs($this->user);

        $reservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'is_recurring' => true,
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $event = CalendarService::reservationToCalendarEvent($reservation);

        expect($event)->toBeInstanceOf(CalendarEvent::class)
            ->and($event->getTitle())->toContain($this->user->name)
            ->and($event->getTitle())->toContain('(Recurring)')
            ->and($event->getExtendedProps()['user_name'])->toBe($this->user->name)
            ->and($event->getExtendedProps()['cost'])->toBe($reservation->cost);
    });

    it('shows limited details for other users reservations', function () {
        $this->actingAs($this->otherUser);

        $reservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $event = CalendarService::reservationToCalendarEvent($reservation);

        expect($event->getTitle())->toBe('Reserved')
            ->and($event->getExtendedProps())->not->toHaveKey('user_name')
            ->and($event->getExtendedProps())->not->toHaveKey('cost');
    });

    it('shows full details for users with view reservations permission', function () {
        $adminUser = $this->createUser();
        $adminUser->givePermissionTo('view reservations');
        $this->actingAs($adminUser);

        $reservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $event = CalendarService::reservationToCalendarEvent($reservation);

        expect($event->getTitle())->toContain($this->user->name)
            ->and($event->getExtendedProps()['user_name'])->toBe($this->user->name);
    });

    it('shows pending status in title', function () {
        $this->actingAs($this->user);

        $reservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $event = CalendarService::reservationToCalendarEvent($reservation);

        expect($event->getTitle())->toContain('(Pending)');
    });

    it('uses correct colors for different statuses', function () {
        $this->actingAs($this->user);

        $confirmedReservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $pendingReservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'reserved_at' => Carbon::now()->addDays(2),
            'reserved_until' => Carbon::now()->addDays(2)->addHours(2),
        ]);

        $cancelledReservation = $this->createReservation([
            'user_id' => $this->user->id,
            'status' => 'cancelled',
            'reserved_at' => Carbon::now()->addDays(3),
            'reserved_until' => Carbon::now()->addDays(3)->addHours(2),
        ]);

        $confirmedEvent = CalendarService::reservationToCalendarEvent($confirmedReservation);
        $pendingEvent = CalendarService::reservationToCalendarEvent($pendingReservation);
        $cancelledEvent = CalendarService::reservationToCalendarEvent($cancelledReservation);

        expect($confirmedEvent->getBackgroundColor())->toBe('#10b981') // green
            ->and($pendingEvent->getBackgroundColor())->toBe('#f59e0b') // yellow
            ->and($cancelledEvent->getBackgroundColor())->toBe('#ef4444'); // red
    });
});

describe('productionToCalendarEvent', function () {
    it('creates calendar event for CMC productions', function () {
        $manager = $this->createUser();
        $production = $this->createProduction([
            'manager_id' => $manager->id,
            'title' => 'Test Show',
            'status' => 'published',
            'start_time' => Carbon::now()->addDay(),
            'end_time' => Carbon::now()->addDay()->addHours(3),
            'published_at' => Carbon::now()->subDay(),
            'location' => \App\Data\LocationData::cmc(),
        ]);

        $event = CalendarService::productionToCalendarEvent($production);

        expect($event)->toBeInstanceOf(CalendarEvent::class)
            ->and($event->getTitle())->toBe('Test Show')
            ->and($event->getExtendedProps()['type'])->toBe('production')
            ->and($event->getExtendedProps()['manager_name'])->toBe($manager->name)
            ->and($event->getExtendedProps()['is_published'])->toBeTrue();
    });

    it('shows draft status for unpublished productions', function () {
        $production = $this->createProduction([
            'title' => 'Draft Show',
            'status' => 'pre-production',
            'start_time' => Carbon::now()->addDay(),
            'end_time' => Carbon::now()->addDay()->addHours(3),
            'published_at' => null,
            'location' => \App\Data\LocationData::cmc(),
        ]);

        $event = CalendarService::productionToCalendarEvent($production);

        expect($event->getTitle())->toBe('Draft Show (Draft)')
            ->and($event->getExtendedProps()['is_published'])->toBeFalse();
    });

    it('hides external venue productions', function () {
        $production = $this->createProduction([
            'title' => 'External Show',
            'start_time' => Carbon::now()->addDay(),
            'end_time' => Carbon::now()->addDay()->addHours(3),
            'location' => \App\Data\LocationData::external('External Venue', '123 Main St'),
        ]);

        $event = CalendarService::productionToCalendarEvent($production);

        expect($event->getDisplay())->toBe('none')
            ->and($event->getTitle())->toBe('');
    });

    it('uses correct colors for different production statuses', function () {
        $statuses = [
            'pre-production' => '#8b5cf6', // purple
            'production' => '#3b82f6',     // blue
            'completed' => '#10b981',      // green
            'cancelled' => '#ef4444',      // red
        ];

        foreach ($statuses as $status => $expectedColor) {
            $production = $this->createProduction([
                'status' => $status,
                'start_time' => Carbon::now()->addDay(),
                'end_time' => Carbon::now()->addDay()->addHours(3),
                'location' => \App\Data\LocationData::cmc(),
            ]);

            $event = CalendarService::productionToCalendarEvent($production);

            expect($event->getBackgroundColor())->toBe($expectedColor, "Failed for status: {$status}");
        }
    });
});

describe('getEventsForDateRange', function () {
    it('returns both reservations and productions in date range', function () {
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->addDays(7)->endOfDay();

        // Create reservations in range
        $reservation1 = $this->createReservation([
            'reserved_at' => Carbon::now()->addDay(),
            'reserved_until' => Carbon::now()->addDay()->addHours(2),
        ]);

        $reservation2 = $this->createReservation([
            'reserved_at' => Carbon::now()->addDays(3),
            'reserved_until' => Carbon::now()->addDays(3)->addHours(2),
        ]);

        // Create production in range
        $production = $this->createProduction([
            'start_time' => Carbon::now()->addDays(5),
            'end_time' => Carbon::now()->addDays(5)->addHours(3),
            'location' => \App\Data\LocationData::cmc(),
        ]);

        // Create items outside range (should be excluded)
        $this->createReservation([
            'reserved_at' => Carbon::now()->addDays(10),
            'reserved_until' => Carbon::now()->addDays(10)->addHours(2),
        ]);

        $events = CalendarService::getEventsForDateRange($startDate, $endDate);

        expect($events)->toHaveCount(3);

        $reservationEvents = array_filter($events, fn($event) => $event->getExtendedProps()['type'] === 'reservation');
        $productionEvents = array_filter($events, fn($event) => $event->getExtendedProps()['type'] === 'production');

        expect($reservationEvents)->toHaveCount(2)
            ->and($productionEvents)->toHaveCount(1);
    });
});

describe('hasConflicts', function () {
    it('detects conflicting reservations', function () {
        $baseTime = Carbon::tomorrow()->setHour(14);

        // Create existing confirmed reservation
        $existingReservation = $this->createReservation([
            'status' => 'confirmed',
            'reserved_at' => $baseTime,
            'reserved_until' => $baseTime->copy()->addHours(2),
        ]);

        // Check for conflicts with overlapping time
        $conflicts = CalendarService::hasConflicts(
            $baseTime->copy()->addHour(),
            $baseTime->copy()->addHours(3)
        );

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['type'])->toBe('reservation')
            ->and($conflicts[0]['model']->id)->toBe($existingReservation->id);
    });

    it('detects conflicting productions at CMC', function () {
        $baseTime = Carbon::tomorrow()->setHour(19);

        // Create existing production at CMC
        $existingProduction = $this->createProduction([
            'start_time' => $baseTime,
            'end_time' => $baseTime->copy()->addHours(3),
            'location' => \App\Data\LocationData::cmc(),
        ]);

        // Check for conflicts
        $conflicts = CalendarService::hasConflicts(
            $baseTime->copy()->addHour(),
            $baseTime->copy()->addHours(2)
        );

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['type'])->toBe('production')
            ->and($conflicts[0]['model']->id)->toBe($existingProduction->id);
    });

    it('ignores external venue productions for conflicts', function () {
        $baseTime = Carbon::tomorrow()->setHour(19);

        // Create production at external venue
        $this->createProduction([
            'start_time' => $baseTime,
            'end_time' => $baseTime->copy()->addHours(3),
            'location' => \App\Data\LocationData::external('External Venue', '123 Main St'),
        ]);

        // Check for conflicts - should be none since external venue
        $conflicts = CalendarService::hasConflicts(
            $baseTime->copy()->addHour(),
            $baseTime->copy()->addHours(2)
        );

        expect($conflicts)->toHaveCount(0);
    });

    it('excludes specified reservation from conflict check', function () {
        $baseTime = Carbon::tomorrow()->setHour(14);

        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'reserved_at' => $baseTime,
            'reserved_until' => $baseTime->copy()->addHours(2),
        ]);

        // Check for conflicts excluding the reservation itself
        $conflicts = CalendarService::hasConflicts(
            $baseTime,
            $baseTime->copy()->addHours(2),
            $reservation->id
        );

        expect($conflicts)->toHaveCount(0);
    });

    it('excludes specified production from conflict check', function () {
        $baseTime = Carbon::tomorrow()->setHour(19);

        $production = $this->createProduction([
            'start_time' => $baseTime,
            'end_time' => $baseTime->copy()->addHours(3),
            'location' => \App\Data\LocationData::cmc(),
        ]);

        // Check for conflicts excluding the production itself
        $conflicts = CalendarService::hasConflicts(
            $baseTime,
            $baseTime->copy()->addHours(3),
            null,
            $production->id
        );

        expect($conflicts)->toHaveCount(0);
    });

    it('only considers confirmed reservations for conflicts', function () {
        $baseTime = Carbon::tomorrow()->setHour(14);

        // Create pending reservation (should not conflict)
        $this->createReservation([
            'status' => 'pending',
            'reserved_at' => $baseTime,
            'reserved_until' => $baseTime->copy()->addHours(2),
        ]);

        // Create cancelled reservation (should not conflict)
        $this->createReservation([
            'status' => 'cancelled',
            'reserved_at' => $baseTime->copy()->addHour(),
            'reserved_until' => $baseTime->copy()->addHours(3),
        ]);

        $conflicts = CalendarService::hasConflicts(
            $baseTime->copy()->addMinutes(30),
            $baseTime->copy()->addHours(2)
        );

        expect($conflicts)->toHaveCount(0);
    });
});
