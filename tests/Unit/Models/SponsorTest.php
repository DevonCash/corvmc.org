<?php

use CorvMC\Sponsorship\Models\Sponsor;
use App\Models\User;

it('has a sponsoredMembers relationship', function () {
    $sponsor = Sponsor::factory()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    expect($sponsor->sponsoredMembers)->toHaveCount(1)
        ->and($sponsor->sponsoredMembers->first()->id)->toBe($user->id);
});

it('calculates used slots correctly', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->usedSlots())->toBe(3);
});

it('calculates available slots correctly for Harmony tier', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->availableSlots())->toBe(2)  // 5 total - 3 used = 2 available
        ->and($sponsor->sponsored_memberships)->toBe(5);
});

it('calculates available slots correctly for Melody tier', function () {
    $sponsor = Sponsor::factory()->melody()->create();
    $users = User::factory()->count(7)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->availableSlots())->toBe(3)  // 10 total - 7 used = 3 available
        ->and($sponsor->sponsored_memberships)->toBe(10);
});

it('calculates available slots correctly for Rhythm tier', function () {
    $sponsor = Sponsor::factory()->rhythm()->create();

    expect($sponsor->availableSlots())->toBe(20)
        ->and($sponsor->sponsored_memberships)->toBe(20);
});

it('calculates available slots correctly for Crescendo tier', function () {
    $sponsor = Sponsor::factory()->crescendo()->create();
    $users = User::factory()->count(25)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->availableSlots())->toBe(0)  // 25 total - 25 used = 0 available
        ->and($sponsor->sponsored_memberships)->toBe(25);
});

it('returns false for hasAvailableSlots when all slots are used', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(5)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->hasAvailableSlots())->toBeFalse();
});

it('returns true for hasAvailableSlots when slots are available', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->hasAvailableSlots())->toBeTrue();
});

it('handles zero used slots correctly', function () {
    $sponsor = Sponsor::factory()->harmony()->create();

    expect($sponsor->usedSlots())->toBe(0)
        ->and($sponsor->availableSlots())->toBe(5)
        ->and($sponsor->hasAvailableSlots())->toBeTrue();
});

it('stores created_at timestamp on pivot table', function () {
    $sponsor = Sponsor::factory()->create();
    $user = User::factory()->create();

    $sponsor->sponsoredMembers()->attach($user->id);

    $pivot = $sponsor->sponsoredMembers()->first()->pivot;

    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('can have multiple sponsored members', function () {
    $sponsor = Sponsor::factory()->crescendo()->create();
    $users = User::factory()->count(10)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($sponsor->sponsoredMembers)->toHaveCount(10)
        ->and($sponsor->usedSlots())->toBe(10)
        ->and($sponsor->availableSlots())->toBe(15);
});

it('user can be sponsored by multiple sponsors', function () {
    $sponsors = Sponsor::factory()->count(3)->create();
    $user = User::factory()->create();

    foreach ($sponsors as $sponsor) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    expect($user->sponsors)->toHaveCount(3);
});
