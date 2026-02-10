<?php

use App\Models\User;
use CorvMC\Membership\Events\MemberProfileCreated;
use CorvMC\Membership\Events\MemberProfileDeleted;
use CorvMC\Membership\Events\MemberProfileUpdated;
use CorvMC\Membership\Models\MemberProfile;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

it('logs activity when a member profile is created', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->create(['user_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    MemberProfileCreated::dispatch($profile);

    $activity = Activity::where('event', 'created')
        ->where('log_name', 'member_profile')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Member profile created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($profile->id);
});

it('logs activity when a member profile is updated', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->create(['user_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    MemberProfileUpdated::dispatch($profile, ['bio', 'hometown'], ['bio' => 'Old bio', 'hometown' => 'Portland']);

    $activity = Activity::where('event', 'updated')
        ->where('log_name', 'member_profile')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Member profile updated: bio, hometown')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties['changed_fields'])->toBe(['bio', 'hometown'])
        ->and($activity->properties['old_values'])->toBe(['bio' => 'Old bio', 'hometown' => 'Portland']);
});

it('logs activity when a member profile is deleted', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->create(['user_id' => $user->id]);

    Activity::query()->delete();

    $this->actingAs($user);
    MemberProfileDeleted::dispatch($profile);

    $activity = Activity::where('event', 'deleted')
        ->where('log_name', 'member_profile')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Member profile deleted')
        ->and($activity->causer_id)->toBe($user->id);
});

describe('No duplicate audit logs', function () {
    it('creates exactly one log entry when creating a profile via action', function () {
        $user = User::factory()->create();

        Activity::query()->delete();

        $this->actingAs($user);
        $profile = \CorvMC\Membership\Actions\MemberProfiles\CreateMemberProfile::run([
            'user_id' => $user->id,
            'bio' => 'Hello world',
        ]);

        $logs = Activity::where('subject_type', 'member_profile')
            ->where('subject_id', $profile->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('member_profile')
            ->and($logs->first()->event)->toBe('created');
    });

    it('creates exactly one log entry when updating a profile via action', function () {
        $user = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Original bio',
        ]);

        Activity::query()->delete();

        $this->actingAs($user);
        \CorvMC\Membership\Actions\MemberProfiles\UpdateMemberProfile::run($profile, ['bio' => 'New bio']);

        $logs = Activity::where('subject_type', 'member_profile')
            ->where('subject_id', $profile->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('member_profile')
            ->and($logs->first()->event)->toBe('updated');
    });
});
