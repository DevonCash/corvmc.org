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
            $reservation = Reservation::factory()->create([
                'user_id' => 999999, // Non-existent user
                'reserved_at' => Carbon::now()->addDay(),
                'reserved_until' => Carbon::now()->addDay()->addHours(2),
            ]);

            expect(fn() => CalendarService::reservationToCalendarEvent($reservation))
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

        it('throws exception for production with null times', function () {
            $manager = $this->createUser();
            $production = Production::factory()->create([
                'manager_id' => $manager->id,
                'start_time' => null,
                'end_time' => Carbon::now()->addDay()->addHours(3),
            ]);

            expect(fn() => CalendarService::productionToCalendarEvent($production))
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

            // Create an invalid reservation with corrupted data
            $invalidReservation = Reservation::factory()->create([
                'user_id' => 999999, // Non-existent user
                'reserved_at' => Carbon::now()->addDay()->setHour(18),
                'reserved_until' => Carbon::now()->addDay()->setHour(20),
            ]);

            // Should return events that can be processed, skip invalid ones
            $events = CalendarService::getEventsForDateRange($start, $end);

            // We should get at least the valid reservation
            expect($events)->toHaveCount(0); // Invalid one gets skipped
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
                ->toThrow(CalendarServiceException::class, 'Failed to generate calendar event');
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
