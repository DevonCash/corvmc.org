<?php

use App\Actions\Sponsors\RevokeSponsoredMembership;
use App\Models\Sponsor;
use App\Models\User;

it('revokes a sponsored membership from a user', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    expect($sponsor->sponsoredMembers)->toHaveCount(1);

    RevokeSponsoredMembership::run($sponsor, $user);

    expect($sponsor->fresh()->sponsoredMembers)->toHaveCount(0);
});

it('throws exception when user is not sponsored by this sponsor', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    RevokeSponsoredMembership::run($sponsor, $user);
})->throws(\Exception::class, 'not sponsored');

it('removes the database record from sponsor_user pivot table', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    $this->assertDatabaseHas('sponsor_user', [
        'sponsor_id' => $sponsor->id,
        'user_id' => $user->id,
    ]);

    RevokeSponsoredMembership::run($sponsor, $user);

    $this->assertDatabaseMissing('sponsor_user', [
        'sponsor_id' => $sponsor->id,
        'user_id' => $user->id,
    ]);
});

it('frees up a slot after revoking', function () {
    $sponsor = Sponsor::factory()->harmony()->create(); // 5 slots
    $users = User::factory()->count(5)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->hasAvailableSlots())->toBeFalse()
        ->and($sponsor->availableSlots())->toBe(0);

    RevokeSponsoredMembership::run($sponsor, $users[0]);

    expect($sponsor->fresh()->hasAvailableSlots())->toBeTrue()
        ->and($sponsor->fresh()->availableSlots())->toBe(1);
});

it('uses a database transaction', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    RevokeSponsoredMembership::run($sponsor, $user);

    // Verify the relationship was removed
    expect($sponsor->fresh()->sponsoredMembers)->toHaveCount(0);
});

it('can revoke one user while keeping others', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    RevokeSponsoredMembership::run($sponsor, $users[1]);

    expect($sponsor->fresh()->sponsoredMembers)->toHaveCount(2)
        ->and($sponsor->fresh()->sponsoredMembers->pluck('id')->toArray())
        ->toContain($users[0]->id, $users[2]->id)
        ->not->toContain($users[1]->id);
});
