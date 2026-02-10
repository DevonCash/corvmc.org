<?php

use App\Models\User;
use CorvMC\Bands\Events\BandCreated;
use CorvMC\Bands\Events\BandDeleted;
use CorvMC\Bands\Events\BandUpdated;
use CorvMC\Bands\Models\Band;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

it('logs activity when a band is created', function () {
    $user = User::factory()->create();
    $band = Band::factory()->create(['name' => 'The Amplifiers', 'owner_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    BandCreated::dispatch($band);

    $activity = Activity::where('event', 'created')
        ->where('log_name', 'band')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Band created: The Amplifiers')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($band->id);
});

it('logs activity when a band is updated', function () {
    $user = User::factory()->create();
    $band = Band::factory()->create(['name' => 'The Amplifiers', 'owner_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    BandUpdated::dispatch($band, ['name', 'bio'], ['name' => 'Old Name', 'bio' => 'Old bio']);

    $activity = Activity::where('event', 'updated')
        ->where('log_name', 'band')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Band updated: name, bio')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['changed_fields'])->toBe(['name', 'bio'])
        ->and($activity->properties['old_values'])->toBe(['name' => 'Old Name', 'bio' => 'Old bio']);
});

it('logs activity when a band is deleted', function () {
    $user = User::factory()->create();
    $band = Band::factory()->create(['name' => 'The Amplifiers', 'owner_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    BandDeleted::dispatch($band);

    $activity = Activity::where('event', 'deleted')
        ->where('log_name', 'band')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Band deleted: The Amplifiers')
        ->and($activity->causer_id)->toBe($user->id);
});

describe('No duplicate audit logs', function () {
    it('creates exactly one log entry when creating a band via action', function () {
        $user = User::factory()->create();

        Activity::query()->delete();

        $this->actingAs($user);
        $band = \CorvMC\Membership\Actions\Bands\CreateBand::run(['name' => 'New Band']);

        $logs = Activity::where('subject_type', 'band')
            ->where('subject_id', $band->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('band')
            ->and($logs->first()->event)->toBe('created');
    });

    it('creates exactly one log entry when updating a band via action', function () {
        $user = User::factory()->create();
        $band = Band::factory()->create(['name' => 'Original Name', 'owner_id' => $user->id]);

        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\Membership\Actions\Bands\UpdateBand::run($band, ['name' => 'Updated Name']);

        $logs = Activity::where('subject_type', 'band')
            ->where('subject_id', $band->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('band')
            ->and($logs->first()->event)->toBe('updated');
    });

    it('creates no log entry when updating non-tracked fields', function () {
        $user = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $user->id]);

        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\Membership\Actions\Bands\UpdateBand::run($band, ['status' => 'active']);

        $logs = Activity::where('subject_type', 'band')
            ->where('subject_id', $band->id)
            ->get();

        expect($logs)->toHaveCount(0);
    });
});
