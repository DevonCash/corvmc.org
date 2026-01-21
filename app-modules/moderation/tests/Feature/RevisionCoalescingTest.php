<?php

use App\Models\MemberProfile;
use CorvMC\Moderation\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('multiple updates coalesce into single pending revision', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    // First update
    $profile->update(['bio' => 'First change']);

    expect($profile->revisions()->count())->toBe(1);
    $revision1 = $profile->revisions()->first();

    // Second update - should coalesce
    $profile->update(['bio' => 'Second change']);

    expect($profile->revisions()->count())->toBe(1); // Still only one revision
    $revision2 = $profile->revisions()->first();

    expect($revision2->id)->toBe($revision1->id); // Same revision
    expect($revision2->original_data['bio'])->toBe('Original'); // Original data unchanged
    expect($revision2->proposed_changes['bio'])->toBe('Second change'); // New value
});

test('coalescing preserves original snapshot', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create([
        'bio' => 'Original bio',
        'hometown' => 'Original town',
    ]);

    Auth::setUser($user);

    // First update changes bio
    $profile->update(['bio' => 'Changed bio']);

    $revision = $profile->revisions()->first();
    expect($revision->original_data['bio'])->toBe('Original bio');
    expect($revision->original_data['hometown'])->toBe('Original town');

    // Second update changes hometown - should merge
    $profile->update(['hometown' => 'New town']);

    $revision->refresh();
    expect($revision->original_data['bio'])->toBe('Original bio');
    expect($revision->original_data['hometown'])->toBe('Original town'); // Original data still unchanged

    expect($revision->proposed_changes)->toMatchArray([
        'bio' => 'Changed bio',
        'hometown' => 'New town',
    ]); // Both changes present
});

test('coalescing updates timestamp', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    $profile->update(['bio' => 'First']);
    $revision = $profile->revisions()->first();
    $firstUpdatedAt = $revision->updated_at;

    sleep(1);

    $profile->update(['bio' => 'Second']);
    $revision->refresh();

    expect($revision->updated_at->isAfter($firstUpdatedAt))->toBeTrue();
});

test('proposed changes are merged correctly', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create([
        'bio' => 'Original bio',
        'hometown' => 'Original City',
    ]);

    Auth::setUser($user);

    // First update: bio
    $profile->update(['bio' => 'New bio']);

    $revision = $profile->revisions()->first();
    expect($revision->proposed_changes)->toMatchArray([
        'bio' => 'New bio',
    ]);

    // Second update: hometown (adds new field)
    $profile->update(['hometown' => 'New City']);

    $revision->refresh();
    expect($revision->proposed_changes)->toMatchArray([
        'bio' => 'New bio',
        'hometown' => 'New City',
    ]);

    // Third update: bio again (overrides previous bio change)
    $profile->update(['bio' => 'Newest bio']);

    $revision->refresh();
    expect($revision->proposed_changes)->toMatchArray([
        'bio' => 'Newest bio',
        'hometown' => 'New City',
    ]);
});

test('different users create separate revisions', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $profile = MemberProfile::factory()->for($user1, 'user')->create(['bio' => 'Original']);

    // User 1 creates revision
    Auth::setUser($user1);
    $profile->update(['bio' => 'User 1 change']);

    // User 2 creates separate revision
    Auth::setUser($user2);
    $profile->update(['bio' => 'User 2 change']);

    expect($profile->revisions()->count())->toBe(2);
});

test('approved revision does not coalesce', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage events');

    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    // First update - auto-approves because user has manage events permission
    $profile->update(['bio' => 'First']);
    $revision1 = $profile->revisions()->first();
    expect($revision1->status)->toBe(Revision::STATUS_APPROVED);

    // Second update - creates new revision (no pending to coalesce)
    $profile->update(['bio' => 'Second']);

    expect($profile->revisions()->count())->toBe(2);
});

test('field value changes multiple times keeps latest value', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    $profile->update(['bio' => 'First']);
    $profile->update(['bio' => 'Second']);
    $profile->update(['bio' => 'Third']);

    $revision = $profile->revisions()->first();

    expect($profile->revisions()->count())->toBe(1);
    expect($revision->original_data['bio'])->toBe('Original');
    expect($revision->proposed_changes['bio'])->toBe('Third'); // Latest value wins
});

test('submission_reason field no longer exists', function () {
    expect(Schema::hasColumn('revisions', 'submission_reason'))->toBeFalse();
});

test('creating revision without submission_reason works', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    $profile->update(['bio' => 'Changed']);

    $revision = $profile->revisions()->first();
    expect($revision)->not->toBeNull();
    expect($revision->status)->toBeIn([Revision::STATUS_PENDING, Revision::STATUS_APPROVED]);
});

test('coalesced revision re-evaluates auto-approval', function () {
    // Create a user without auto-approval permissions
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    // First update - should create pending revision
    $profile->update(['bio' => 'First change']);

    $revision = $profile->revisions()->first();
    expect($revision->status)->toBe(Revision::STATUS_PENDING);
    expect($revision->auto_approved)->toBeFalse();

    // Give user auto-approval permission
    $user->givePermissionTo('manage events');

    // Update again - should coalesce and auto-approve
    $profile->update(['bio' => 'Second change']);

    $revision->refresh();
    expect($revision->status)->toBe(Revision::STATUS_APPROVED);
    expect($revision->auto_approved)->toBeTrue();
    expect($revision->review_reason)->toContain('Auto-approved based on user trust level');
    expect($revision->review_reason)->toContain('after coalescing additional changes');
});

test('pending revision stays pending if still does not qualify after coalescing', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    $profile->update(['bio' => 'First']);
    $revision = $profile->revisions()->first();
    expect($revision->status)->toBe(Revision::STATUS_PENDING);

    $profile->update(['bio' => 'Second']);
    $revision->refresh();

    // Still pending because user doesn't have auto-approve permissions
    expect($revision->status)->toBe(Revision::STATUS_PENDING);
});

test('coalescing works within transaction safety', function () {
    $user = User::factory()->create();
    $profile = MemberProfile::factory()->for($user, 'user')->create(['bio' => 'Original']);

    Auth::setUser($user);

    // Make multiple rapid updates (simulating race condition)
    $profile->update(['bio' => 'First']);

    $revision = $profile->revisions()->first();
    $originalRevisionId = $revision->id;

    $profile->update(['bio' => 'Second']);
    $profile->update(['bio' => 'Third']);

    $revision->refresh();

    // Should still be the same revision
    expect($revision->id)->toBe($originalRevisionId);
    expect($profile->revisions()->count())->toBe(1);
    expect($revision->proposed_changes['bio'])->toBe('Third');
});
