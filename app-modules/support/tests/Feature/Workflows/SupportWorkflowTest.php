<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\Support\Actions\CalculateOccurrences;
use CorvMC\Support\Actions\CancelRecurringSeries;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Support Workflow: Calculate Occurrences', function () {
    it('calculates weekly occurrences from RRULE', function () {
        $start = Carbon::parse('2025-01-06', config('app.timezone')); // Monday
        $end = Carbon::parse('2025-01-31', config('app.timezone'));

        // Weekly on Mondays
        $occurrences = CalculateOccurrences::run('FREQ=WEEKLY;BYDAY=MO', $start, $end);

        expect($occurrences)->toHaveCount(4);
        expect($occurrences[0]->format('Y-m-d'))->toBe('2025-01-06');
        expect($occurrences[1]->format('Y-m-d'))->toBe('2025-01-13');
        expect($occurrences[2]->format('Y-m-d'))->toBe('2025-01-20');
        expect($occurrences[3]->format('Y-m-d'))->toBe('2025-01-27');
    });

    it('calculates bi-weekly occurrences', function () {
        $start = Carbon::parse('2025-01-07', config('app.timezone')); // Tuesday
        $end = Carbon::parse('2025-02-28', config('app.timezone'));

        // Bi-weekly on Tuesdays
        $occurrences = CalculateOccurrences::run('FREQ=WEEKLY;INTERVAL=2;BYDAY=TU', $start, $end);

        expect($occurrences)->toHaveCount(4);
        expect($occurrences[0]->format('Y-m-d'))->toBe('2025-01-07');
        expect($occurrences[1]->format('Y-m-d'))->toBe('2025-01-21');
        expect($occurrences[2]->format('Y-m-d'))->toBe('2025-02-04');
        expect($occurrences[3]->format('Y-m-d'))->toBe('2025-02-18');
    });

    it('calculates monthly occurrences', function () {
        $start = Carbon::parse('2025-01-15', config('app.timezone'));
        $end = Carbon::parse('2025-06-30', config('app.timezone'));

        // Monthly on the 15th
        $occurrences = CalculateOccurrences::run('FREQ=MONTHLY;BYMONTHDAY=15', $start, $end);

        expect($occurrences)->toHaveCount(6);
        expect($occurrences[0]->format('Y-m-d'))->toBe('2025-01-15');
        expect($occurrences[5]->format('Y-m-d'))->toBe('2025-06-15');
    });

    it('respects start and end date boundaries', function () {
        $start = Carbon::parse('2025-01-15', config('app.timezone'));
        $end = Carbon::parse('2025-01-25', config('app.timezone'));

        // Weekly on Mondays
        $occurrences = CalculateOccurrences::run('FREQ=WEEKLY;BYDAY=MO', $start, $end);

        // Only Jan 20 should be in range (Jan 13 before start, Jan 27 after end)
        expect($occurrences)->toHaveCount(1);
        expect($occurrences[0]->format('Y-m-d'))->toBe('2025-01-20');
    });

    it('returns empty array when no occurrences in range', function () {
        $start = Carbon::parse('2025-01-01', config('app.timezone')); // Wednesday
        $end = Carbon::parse('2025-01-02', config('app.timezone')); // Thursday

        // Weekly on Fridays - neither Wed nor Thu is Friday
        $occurrences = CalculateOccurrences::run('FREQ=WEEKLY;BYDAY=FR', $start, $end);

        expect($occurrences)->toBeEmpty();
    });
});

describe('Support Workflow: Generate Recurring Instances', function () {
    it('generates instances up to max_advance_days', function () {
        $user = User::factory()->sustainingMember()->create();

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => RehearsalReservation::class,
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
            'series_start_date' => now()->startOfWeek()->addDay(), // Tuesday
            'series_end_date' => null,
            'max_advance_days' => 14, // Only 2 weeks ahead
            'status' => RecurringSeriesStatus::ACTIVE,
        ]);

        $created = GenerateRecurringInstances::run($series);

        // Should create instances for next ~2 weeks of Tuesdays
        expect($created->count())->toBeLessThanOrEqual(3);
        expect($created->count())->toBeGreaterThan(0);

        // Verify instances are RehearsalReservations
        foreach ($created as $instance) {
            expect($instance)->toBeInstanceOf(Reservation::class);
            expect($instance->recurring_series_id)->toBe($series->id);
        }
    });

    it('respects series_end_date', function () {
        $user = User::factory()->sustainingMember()->create();

        $startDate = now()->startOfWeek()->addDay(); // Tuesday
        $endDate = $startDate->copy()->addWeeks(2);

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => RehearsalReservation::class,
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
            'series_start_date' => $startDate,
            'series_end_date' => $endDate,
            'max_advance_days' => 90,
            'status' => RecurringSeriesStatus::ACTIVE,
        ]);

        $created = GenerateRecurringInstances::run($series);

        // Should create exactly 3 instances (start + 2 weeks)
        expect($created->count())->toBeLessThanOrEqual(3);

        // All instances should be on or before end date
        foreach ($created as $instance) {
            $instanceDate = Carbon::parse($instance->instance_date);
            expect($instanceDate->lte($endDate))->toBeTrue();
        }
    });

    it('skips dates where instance already exists', function () {
        $user = User::factory()->sustainingMember()->create();

        // Use a fixed future date far enough to avoid same-day validation issues
        $fixedStartDate = Carbon::parse('2025-03-04', config('app.timezone')); // A Tuesday
        $fixedEndDate = $fixedStartDate->copy()->addWeeks(2);

        // Freeze time to ensure consistent behavior
        $this->travelTo($fixedStartDate->copy()->subDays(7)); // One week before start

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => RehearsalReservation::class,
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
            'series_start_date' => $fixedStartDate,
            'series_end_date' => $fixedEndDate,
            'max_advance_days' => 21,
            'status' => RecurringSeriesStatus::ACTIVE,
        ]);

        // Generate first batch
        $firstBatch = GenerateRecurringInstances::run($series);
        expect($firstBatch->count())->toBeGreaterThan(0);

        // Count instances for this specific series
        $countAfterFirstBatch = Reservation::where('recurring_series_id', $series->id)->count();

        // Generate again - should not create duplicates
        $secondBatch = GenerateRecurringInstances::run($series);

        expect($secondBatch->count())->toBe(0);

        // Verify no new instances were created for this series
        $countAfterSecondBatch = Reservation::where('recurring_series_id', $series->id)->count();
        expect($countAfterSecondBatch)->toBe($countAfterFirstBatch);
    });
});

describe('Support Workflow: Cancel Recurring Series', function () {
    it('cancels series and sets status to cancelled', function () {
        $user = User::factory()->sustainingMember()->create();

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => RehearsalReservation::class,
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=WE',
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'series_start_date' => now()->startOfWeek()->addDays(2), // Wednesday
            'series_end_date' => null,
            'max_advance_days' => 30,
            'status' => RecurringSeriesStatus::ACTIVE,
        ]);

        // Generate some instances
        GenerateRecurringInstances::run($series);

        CancelRecurringSeries::run($series, 'User requested cancellation');

        $series->refresh();
        expect($series->status)->toBe(RecurringSeriesStatus::CANCELLED);
    });

    it('cancels all future instances with reason', function () {
        $user = User::factory()->sustainingMember()->create();

        $startDate = now()->addDays(1)->startOfDay();

        $series = RecurringSeries::create([
            'user_id' => $user->id,
            'recurable_type' => RehearsalReservation::class,
            'recurrence_rule' => 'FREQ=DAILY',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'series_start_date' => $startDate,
            'series_end_date' => null,
            'max_advance_days' => 7,
            'status' => RecurringSeriesStatus::ACTIVE,
        ]);

        // Generate instances
        $created = GenerateRecurringInstances::run($series);
        $instanceCount = $created->count();

        // Cancel with reason
        CancelRecurringSeries::run($series, 'Schedule conflict');

        // Verify all future instances are cancelled
        $cancelledInstances = Reservation::where('recurring_series_id', $series->id)
            ->where('status', 'cancelled')
            ->get();

        expect($cancelledInstances->count())->toBe($instanceCount);

        // Verify cancellation reason
        foreach ($cancelledInstances as $instance) {
            expect($instance->cancellation_reason)->toBe('Schedule conflict');
        }
    });
});
