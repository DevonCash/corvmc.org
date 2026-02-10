<?php

use App\Models\User;
use CorvMC\Membership\Events\UserCreated;
use CorvMC\Membership\Events\UserDeleted;
use CorvMC\Membership\Events\UserUpdated;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

it('logs activity when a user is created', function () {
    $user = User::factory()->create(['name' => 'Jordan Martinez']);

    Activity::query()->delete();

    UserCreated::dispatch($user);

    $activity = Activity::where('event', 'created')
        ->where('log_name', 'user')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('User account created: Jordan Martinez')
        ->and($activity->subject_id)->toBe($user->id);
});

it('logs activity when a user is updated', function () {
    $user = User::factory()->create(['name' => 'Jordan Martinez']);

    Activity::query()->delete();

    $this->actingAs($user);
    UserUpdated::dispatch($user, ['name', 'email'], ['name' => 'Old Name', 'email' => 'old@example.com']);

    $activity = Activity::where('event', 'updated')
        ->where('log_name', 'user')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('User account updated: name, email')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['changed_fields'])->toBe(['name', 'email'])
        ->and($activity->properties['old_values'])->toBe(['name' => 'Old Name', 'email' => 'old@example.com']);
});

it('logs activity when a user is deleted', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create(['name' => 'Jordan Martinez']);

    Activity::query()->delete();

    $this->actingAs($admin);
    UserDeleted::dispatch($user);

    $activity = Activity::where('event', 'deleted')
        ->where('log_name', 'user')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('User account deleted: Jordan Martinez')
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($user->id);
});

describe('No duplicate audit logs', function () {
    it('creates exactly one log entry when creating a user via action', function () {
        Activity::query()->delete();

        $user = \CorvMC\Membership\Actions\Users\CreateUser::run([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $logs = Activity::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('user')
            ->and($logs->first()->event)->toBe('created');
    });

    it('creates exactly one log entry when updating a user via action', function () {
        $user = User::factory()->create(['name' => 'Original Name']);
        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\Membership\Actions\Users\UpdateUser::run($user, ['name' => 'New Name']);

        $logs = Activity::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('user')
            ->and($logs->first()->event)->toBe('updated');
    });

    it('creates exactly one log entry when deleting a user via action', function () {
        $admin = User::factory()->create();
        $user = User::factory()->create(['name' => 'Delete Me']);
        Activity::query()->delete();

        $this->actingAs($admin);
        \CorvMC\Membership\Actions\Users\DeleteUser::run($user);

        $logs = Activity::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('user')
            ->and($logs->first()->event)->toBe('deleted');
    });

    it('creates no log entry when updating non-tracked fields', function () {
        $user = User::factory()->create();
        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\Membership\Actions\Users\UpdateUser::run($user, ['password' => 'newpassword123']);

        $logs = Activity::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->get();

        expect($logs)->toHaveCount(0);
    });
});
