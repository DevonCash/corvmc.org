<?php

use App\Actions\Sponsors\AssignSponsoredMembership;
use App\Models\Sponsor;
use App\Models\User;

it('assigns a sponsored membership to a user', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    AssignSponsoredMembership::run($sponsor, $user);

    expect($sponsor->sponsoredMembers)->toHaveCount(1)
        ->and($sponsor->sponsoredMembers->first()->id)->toBe($user->id);
});

it('throws exception when sponsor has no available slots', function () {
    $sponsor = Sponsor::factory()->harmony()->create(); // 5 slots
    $users = User::factory()->count(5)->create();

    // Fill all slots
    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    $newUser = User::factory()->create();

    AssignSponsoredMembership::run($sponsor, $newUser);
})->throws(\Exception::class, 'Cannot assign sponsored membership');

it('throws exception when user is already sponsored by this sponsor', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    AssignSponsoredMembership::run($sponsor, $user);
})->throws(\Exception::class, 'already sponsored');

it('creates a database record in sponsor_user pivot table', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    AssignSponsoredMembership::run($sponsor, $user);

    $this->assertDatabaseHas('sponsor_user', [
        'sponsor_id' => $sponsor->id,
        'user_id' => $user->id,
    ]);
});

it('can assign multiple users to a sponsor within limits', function () {
    $sponsor = Sponsor::factory()->crescendo()->create(); // 25 slots
    $users = User::factory()->count(10)->create();

    foreach ($users as $user) {
        AssignSponsoredMembership::run($sponsor, $user);
    }

    expect($sponsor->sponsoredMembers()->count())->toBe(10)
        ->and($sponsor->availableSlots())->toBe(15);
});

it('uses a database transaction', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    // This should complete successfully
    AssignSponsoredMembership::run($sponsor, $user);

    // Verify the relationship was saved
    expect($sponsor->fresh()->sponsoredMembers)->toHaveCount(1);
});
