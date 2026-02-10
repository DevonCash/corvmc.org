<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Events\RecurringSeriesCancelled;
use CorvMC\Support\Events\RecurringSeriesCreated;
use CorvMC\Support\Events\RecurringSeriesExtended;
use CorvMC\Support\Events\RecurringSeriesPaused;
use CorvMC\Support\Events\RecurringSeriesResumed;
use CorvMC\Support\Models\RecurringSeries;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

function createTestRecurringSeries(User $user, array $overrides = []): RecurringSeries
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

it('logs activity when a recurring series is created', function () {
    $user = User::factory()->sustainingMember()->create();
    $series = createTestRecurringSeries($user);

    Activity::query()->delete();

    $this->actingAs($user);
    RecurringSeriesCreated::dispatch($series);

    $activity = Activity::where('event', 'created')
        ->where('log_name', 'recurring_series')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Recurring series created:')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($series->id)
        ->and($activity->properties)->toHaveKey('recurrence_rule');
});

it('logs activity when a recurring series is cancelled', function () {
    $user = User::factory()->sustainingMember()->create();
    $series = createTestRecurringSeries($user);

    Activity::query()->delete();

    $this->actingAs($user);
    RecurringSeriesCancelled::dispatch($series);

    $activity = Activity::where('event', 'cancelled')
        ->where('log_name', 'recurring_series')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Recurring series cancelled')
        ->and($activity->causer_id)->toBe($user->id);
});

it('logs activity when a recurring series is paused', function () {
    $user = User::factory()->sustainingMember()->create();
    $series = createTestRecurringSeries($user);

    Activity::query()->delete();

    $this->actingAs($user);
    RecurringSeriesPaused::dispatch($series);

    $activity = Activity::where('event', 'paused')
        ->where('log_name', 'recurring_series')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Recurring series paused')
        ->and($activity->causer_id)->toBe($user->id);
});

it('logs activity when a recurring series is resumed', function () {
    $user = User::factory()->sustainingMember()->create();
    $series = createTestRecurringSeries($user);

    Activity::query()->delete();

    $this->actingAs($user);
    RecurringSeriesResumed::dispatch($series);

    $activity = Activity::where('event', 'resumed')
        ->where('log_name', 'recurring_series')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Recurring series resumed')
        ->and($activity->causer_id)->toBe($user->id);
});

it('logs activity when a recurring series is extended', function () {
    $user = User::factory()->sustainingMember()->create();
    $series = createTestRecurringSeries($user, [
        'series_end_date' => now()->addMonths(2),
    ]);
    $previousEndDate = $series->series_end_date->copy();
    $series->update(['series_end_date' => now()->addMonths(4)]);

    Activity::query()->delete();

    $this->actingAs($user);
    RecurringSeriesExtended::dispatch($series, $previousEndDate);

    $activity = Activity::where('event', 'extended')
        ->where('log_name', 'recurring_series')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Recurring series extended to')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties)->toHaveKey('previous_end_date')
        ->and($activity->properties)->toHaveKey('new_end_date');
});

describe('No duplicate audit logs', function () {
    it('creates exactly one log entry when pausing a series via action', function () {
        $user = User::factory()->sustainingMember()->create();
        $series = createTestRecurringSeries($user);

        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\SpaceManagement\Actions\RecurringReservations\PauseRecurringSeries::run($series);

        $logs = Activity::where('subject_type', 'recurring_series')
            ->where('subject_id', $series->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('recurring_series')
            ->and($logs->first()->event)->toBe('paused');
    });

    it('creates exactly one log entry when resuming a series via action', function () {
        $user = User::factory()->sustainingMember()->create();
        $series = createTestRecurringSeries($user, ['status' => RecurringSeriesStatus::PAUSED]);

        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\SpaceManagement\Actions\RecurringReservations\ResumeRecurringSeries::run($series);

        $logs = Activity::where('subject_type', 'recurring_series')
            ->where('subject_id', $series->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('recurring_series')
            ->and($logs->first()->event)->toBe('resumed');
    });
});
