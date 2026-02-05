<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\RecurringReservations\GenerateFutureRecurringInstances;
use CorvMC\SpaceManagement\Actions\RecurringReservations\ValidateRecurringPattern;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\Support\Actions\CancelRecurringSeries;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

function createTestSeries(User $user, array $overrides = []): RecurringSeries
{
    return RecurringSeries::create(array_merge([
        'user_id' => $user->id,
        'recurable_type' => 'rehearsal_reservation',
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'series_start_date' => now()->addDay()->startOfDay(),
        'series_end_date' => now()->addMonths(2),
        'max_advance_days' => 30,
        'status' => RecurringSeriesStatus::ACTIVE,
    ], $overrides));
}

describe('GenerateRecurringInstances', function () {
    it('creates reservation instances for each occurrence date', function () {
        $user = User::factory()->sustainingMember()->create();

        // Start next Tuesday, run for 4 weeks
        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(4),
            'max_advance_days' => 30,
        ]);

        $created = GenerateRecurringInstances::run($series);

        expect($created->count())->toBeGreaterThan(0);

        // Each created instance should be a reservation linked to the series
        $created->each(function ($reservation) use ($series, $user) {
            expect($reservation)
                ->toBeInstanceOf(RehearsalReservation::class)
                ->recurring_series_id->toBe($series->id)
                ->is_recurring->toBeTrue()
                ->status->toBe(ReservationStatus::Reserved);

            expect($reservation->reserved_at->format('H:i'))->toBe('14:00');
            expect($reservation->reserved_until->format('H:i'))->toBe('16:00');
        });
    });

    it('skips dates that already have an instance', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(4),
            'max_advance_days' => 30,
        ]);

        // Generate once
        $firstRun = GenerateRecurringInstances::run($series);
        $firstCount = $firstRun->count();

        // Generate again — should create no new instances
        $secondRun = GenerateRecurringInstances::run($series);

        expect($secondRun)->toHaveCount(0);

        // Total reservations should not have changed
        $total = Reservation::where('recurring_series_id', $series->id)->count();
        expect($total)->toBe($firstCount);
    });

    it('creates cancelled placeholders when conflicts exist', function () {
        $user = User::factory()->sustainingMember()->create();
        $otherUser = User::factory()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing reservation that conflicts with the first Tuesday
        RehearsalReservation::create([
            'user_id' => $otherUser->id,
            'reservable_type' => 'user',
            'reservable_id' => $otherUser->id,
            'reserved_at' => $nextTuesday->copy()->setTime(14, 0),
            'reserved_until' => $nextTuesday->copy()->setTime(16, 0),
            'hours_used' => 2,
            'status' => ReservationStatus::Confirmed,
        ]);

        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(3),
            'max_advance_days' => 30,
        ]);

        $created = GenerateRecurringInstances::run($series);

        // The conflicting date should have a cancelled placeholder
        $placeholder = Reservation::where('recurring_series_id', $series->id)
            ->where('status', ReservationStatus::Cancelled)
            ->whereDate('instance_date', $nextTuesday)
            ->first();

        expect($placeholder)->not->toBeNull();
        expect($placeholder->cancellation_reason)->toBe('Scheduling conflict');
    });

    it('respects max_advance_days limit', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addMonths(6),
            'max_advance_days' => 14, // Only 2 weeks out
        ]);

        $created = GenerateRecurringInstances::run($series);

        // All instances should be within 14 days of now
        $maxDate = now()->addDays(14);
        $created->each(function ($reservation) use ($maxDate) {
            expect($reservation->reserved_at->lte($maxDate))->toBeTrue();
        });
    });

    it('respects series_end_date', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $endDate = $nextTuesday->copy()->addWeeks(2);

        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $endDate,
            'max_advance_days' => 90,
        ]);

        $created = GenerateRecurringInstances::run($series);

        // No instances should be after the series end date
        $created->each(function ($reservation) use ($endDate) {
            expect($reservation->reserved_at->startOfDay()->lte($endDate))->toBeTrue();
        });
    });
});

describe('CancelRecurringSeries', function () {
    it('cancels the series and all future instances', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(4),
            'max_advance_days' => 30,
        ]);

        // Generate instances first
        GenerateRecurringInstances::run($series);
        $instanceCount = Reservation::where('recurring_series_id', $series->id)
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->count();
        expect($instanceCount)->toBeGreaterThan(0);

        // Cancel the series
        CancelRecurringSeries::run($series, 'User requested cancellation');

        $series->refresh();
        expect($series->status)->toBe(RecurringSeriesStatus::CANCELLED);

        // All future instances should be cancelled
        $activeInstances = Reservation::where('recurring_series_id', $series->id)
            ->where('reserved_at', '>', now())
            ->whereIn('status', [
                ReservationStatus::Scheduled,
                ReservationStatus::Reserved,
                ReservationStatus::Confirmed,
            ])
            ->count();

        expect($activeInstances)->toBe(0);
    });
});

describe('GenerateFutureRecurringInstances', function () {
    it('generates instances for all active reservation series', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(4),
            'max_advance_days' => 30,
        ]);

        // Run the batch action
        GenerateFutureRecurringInstances::run();

        // Should have generated instances
        $instanceCount = Reservation::where('recurring_series_id', $series->id)->count();
        expect($instanceCount)->toBeGreaterThan(0);
    });

    it('skips cancelled series', function () {
        $user = User::factory()->sustainingMember()->create();

        $nextTuesday = now()->next('Tuesday')->startOfDay();
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addWeeks(4),
            'max_advance_days' => 30,
            'status' => RecurringSeriesStatus::CANCELLED,
        ]);

        GenerateFutureRecurringInstances::run();

        $instanceCount = Reservation::where('recurring_series_id', $series->id)->count();
        expect($instanceCount)->toBe(0);
    });

    it('skips series past their end date', function () {
        $user = User::factory()->sustainingMember()->create();

        $series = createTestSeries($user, [
            'series_start_date' => now()->subMonths(3),
            'series_end_date' => now()->subDay(),
            'max_advance_days' => 30,
        ]);

        GenerateFutureRecurringInstances::run();

        $instanceCount = Reservation::where('recurring_series_id', $series->id)->count();
        expect($instanceCount)->toBe(0);
    });
});

describe('ValidateRecurringPattern', function () {
    it('returns no warnings when there are no conflicts', function () {
        $nextMonday = now()->next('Monday')->startOfDay();

        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=MO',
            $nextMonday,
            $nextMonday->copy()->addWeeks(4),
            '10:00:00',
            '12:00:00'
        );

        expect($result['warnings'])->toBeEmpty();
        expect($result['errors'])->toBeEmpty();
    });

    it('detects conflicts with existing reservations', function () {
        $user = User::factory()->create();
        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing reservation on the first Tuesday
        RehearsalReservation::create([
            'user_id' => $user->id,
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $nextTuesday->copy()->setTime(14, 0),
            'reserved_until' => $nextTuesday->copy()->setTime(16, 0),
            'hours_used' => 2,
            'status' => ReservationStatus::Confirmed,
        ]);

        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=TU',
            $nextTuesday,
            $nextTuesday->copy()->addWeeks(4),
            '14:00:00',
            '16:00:00'
        );

        expect($result['warnings'])->not->toBeEmpty();
        expect($result['warnings']->first()['type'])->toBe('existing');
    });

    it('detects conflicts with other recurring series', function () {
        $user = User::factory()->sustainingMember()->create();
        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing recurring series on Tuesdays
        createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addMonths(2),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        // Try to create another series at the same time
        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=TU',
            $nextTuesday,
            $nextTuesday->copy()->addWeeks(4),
            '14:00:00',
            '16:00:00'
        );

        expect($result['warnings'])->not->toBeEmpty();
        expect($result['warnings']->first()['type'])->toBe('recurring');
    });

    it('detects partial time overlaps with recurring series', function () {
        $user = User::factory()->sustainingMember()->create();
        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing recurring series 2-4 PM
        createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addMonths(2),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        // Try to create a series 3-5 PM (overlaps by 1 hour)
        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=TU',
            $nextTuesday,
            $nextTuesday->copy()->addWeeks(4),
            '15:00:00',
            '17:00:00'
        );

        expect($result['warnings'])->not->toBeEmpty();
        expect($result['warnings']->first()['type'])->toBe('recurring');
    });

    it('does not flag non-overlapping times on same day', function () {
        $user = User::factory()->sustainingMember()->create();
        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing recurring series 2-4 PM
        createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addMonths(2),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        // Try to create a series 10 AM - 12 PM (no overlap)
        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=TU',
            $nextTuesday,
            $nextTuesday->copy()->addWeeks(4),
            '10:00:00',
            '12:00:00'
        );

        expect($result['warnings'])->toBeEmpty();
    });

    it('excludes the current series when editing', function () {
        $user = User::factory()->sustainingMember()->create();
        $nextTuesday = now()->next('Tuesday')->startOfDay();

        // Create an existing recurring series
        $series = createTestSeries($user, [
            'series_start_date' => $nextTuesday,
            'series_end_date' => $nextTuesday->copy()->addMonths(2),
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        // Validate with the same pattern, excluding the series itself
        $result = ValidateRecurringPattern::run(
            'FREQ=WEEKLY;BYDAY=TU',
            $nextTuesday,
            $nextTuesday->copy()->addWeeks(4),
            '14:00:00',
            '16:00:00',
            excludeSeriesId: $series->id
        );

        expect($result['warnings'])->toBeEmpty();
    });
});
