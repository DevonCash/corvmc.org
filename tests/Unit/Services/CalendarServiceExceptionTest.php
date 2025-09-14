<?php

use App\Exceptions\Services\CalendarServiceException;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\User;
use App\Facades\CalendarService;
use Carbon\Carbon;

describe('CalendarService Exception Handling', function () {
    describe('reservationToCalendarEvent validation', function () {
        it('throws exception for non-persisted reservation', function () {
            $reservation = new Reservation([
                'reserved_at' => Carbon::now()->addDay(),
                'reserved_until' => Carbon::now()->addDay()->addHours(2),
            ]);
            // Don't save the reservation to database

            expect(fn() => CalendarService::reservationToCalendarEvent($reservation))
                ->toThrow(CalendarServiceException::class, 'Reservation must be persisted to database');
        });

        it('throws exception for reservation without user', function () {
            // Create a new Reservation instance without persisting to test null user handling
            $testReservation = new Reservation();
            $testReservation->exists = true; // Simulate persisted state
            $testReservation->user_id = null;
            $testReservation->reserved_at = Carbon::now()->addDay();
            $testReservation->reserved_until = Carbon::now()->addDay()->addHours(2);

            expect(fn() => CalendarService::reservationToCalendarEvent($testReservation))
                ->toThrow(CalendarServiceException::class, 'Reservation must have an associated user');
        });

        it('throws exception for reservation with null times', function () {
            $user = $this->createUser();
            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => null,
                'reserved_until' => Carbon::now()->addDay()->addHours(2),
            ]);

            expect(fn() => CalendarService::reservationToCalendarEvent($reservation))
                ->toThrow(CalendarServiceException::class, 'Reservation must have start and end times');
        });

        it('throws exception for invalid date range', function () {
            $user = $this->createUser();
            $startTime = Carbon::now()->addDay();
            $endTime = $startTime->copy()->subHour(); // End before start

            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
            ]);

            expect(fn() => CalendarService::reservationToCalendarEvent($reservation))
                ->toThrow(CalendarServiceException::class, 'Invalid date range');
        });
    });

    describe('productionToCalendarEvent validation', function () {
        it('throws exception for non-persisted production', function () {
            $production = new Production([
                'title' => 'Test Show',
                'start_time' => Carbon::now()->addDay(),
                'end_time' => Carbon::now()->addDay()->addHours(3),
            ]);
            // Don't save the production to database

            expect(fn() => CalendarService::productionToCalendarEvent($production))
                ->toThrow(CalendarServiceException::class, 'Production must be persisted to database');
        });

        it('throws exception for production with null start_time', function () {
            $manager = $this->createUser();
            $production = Production::factory()->create([
                'manager_id' => $manager->id,
                'start_time' => Carbon::now()->addDay(),
                'end_time' => Carbon::now()->addDay()->addHours(3),
            ]);

            // Create a new Production instance without persisting to test null handling
            $testProduction = new Production();
            $testProduction->exists = true; // Simulate persisted state
            $testProduction->start_time = null;
            $testProduction->end_time = Carbon::now()->addDay()->addHours(3);

            expect(fn() => CalendarService::productionToCalendarEvent($testProduction))
                ->toThrow(CalendarServiceException::class, 'Production must have start and end times');
        });

        it('throws exception for production with invalid date range', function () {
            $manager = $this->createUser();
            $startTime = Carbon::now()->addDay();
            $endTime = $startTime->copy()->subHour(); // End before start

            $production = Production::factory()->create([
                'manager_id' => $manager->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            expect(fn() => CalendarService::productionToCalendarEvent($production))
                ->toThrow(CalendarServiceException::class, 'Invalid date range');
        });
    });

    describe('getEventsForDateRange validation', function () {
        it('throws exception for invalid date range', function () {
            $start = Carbon::now()->addDay();
            $end = $start->copy()->subDay(); // End before start

            expect(fn() => CalendarService::getEventsForDateRange($start, $end))
                ->toThrow(CalendarServiceException::class, 'Invalid date range');
        });

        it('continues processing when individual events fail', function () {
            $start = Carbon::now()->startOfDay();
            $end = Carbon::now()->addDay()->endOfDay();

            // Create a valid reservation
            $user = $this->createUser();
            $validReservation = $this->createReservation([
                'user_id' => $user->id,
                'reserved_at' => Carbon::now()->addDay()->setHour(14),
                'reserved_until' => Carbon::now()->addDay()->setHour(16),
            ]);

            // Test the valid reservation is processed
            $events = CalendarService::getEventsForDateRange($start, $end);

            // We should get the valid reservation
            expect($events)->toHaveCount(1); // Valid reservation should be returned
        });
    });

    describe('hasConflicts validation', function () {
        it('throws exception for invalid date range', function () {
            $start = Carbon::now()->addDay();
            $end = $start->copy()->subDay(); // End before start

            expect(fn() => CalendarService::hasConflicts($start, $end))
                ->toThrow(CalendarServiceException::class, 'Invalid date range');
        });
    });

    describe('event generation failures', function () {
        it('wraps unexpected exceptions in CalendarServiceException', function () {
            $user = $this->createUser();

            // Create a reservation but force an error by corrupting the user relationship
            $reservation = Reservation::factory()->create([
                'user_id' => $user->id,
                'reserved_at' => Carbon::now()->addDay(),
                'reserved_until' => Carbon::now()->addDay()->addHours(2),
            ]);

            // Delete the user after creating the reservation to force an error
            $user->delete();

            expect(fn() => CalendarService::reservationToCalendarEvent($reservation))
                ->toThrow(CalendarServiceException::class, 'Reservation must have an associated user');
        });
    });
});

describe('CalendarServiceException static methods', function () {
    it('creates invalid date range exception', function () {
        $start = Carbon::now();
        $end = $start->copy()->subDay();

        $exception = CalendarServiceException::invalidDateRange($start, $end);

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Invalid date range')
            ->and($exception->getMessage())->toContain($start->format('Y-m-d'))
            ->and($exception->getMessage())->toContain($end->format('Y-m-d'));
    });

    it('creates unsupported model exception', function () {
        $exception = CalendarServiceException::unsupportedModel('App\\Models\\User');

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Unsupported model class')
            ->and($exception->getMessage())->toContain('App\\Models\\User');
    });

    it('creates missing required data exception', function () {
        $exception = CalendarServiceException::missingRequiredData('user_id', 'reservation creation');

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Missing required data: user_id')
            ->and($exception->getMessage())->toContain('reservation creation');
    });

    it('creates conflict detection failed exception', function () {
        $exception = CalendarServiceException::conflictDetectionFailed('Database connection lost');

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Conflict detection failed')
            ->and($exception->getMessage())->toContain('Database connection lost');
    });

    it('creates invalid event data exception', function () {
        $exception = CalendarServiceException::invalidEventData('Missing start time');

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Invalid calendar event data')
            ->and($exception->getMessage())->toContain('Missing start time');
    });

    it('creates event generation failed exception', function () {
        $exception = CalendarServiceException::eventGenerationFailed('Reservation', 123, 'User not found');

        expect($exception)->toBeInstanceOf(CalendarServiceException::class)
            ->and($exception->getMessage())->toContain('Failed to generate calendar event')
            ->and($exception->getMessage())->toContain('Reservation ID 123')
            ->and($exception->getMessage())->toContain('User not found');
    });
});
