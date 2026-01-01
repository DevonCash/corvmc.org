<?php

use App\Actions\Sponsors\GetSponsorAvailableSlots;
use App\Models\Sponsor;
use App\Models\User;

it('returns correct slot data for sponsor with no sponsored members', function () {
    $sponsor = Sponsor::factory()->harmony()->create();

    $result = GetSponsorAvailableSlots::run($sponsor);

    expect($result)->toBe([
        'total' => 5,
        'used' => 0,
        'available' => 5,
        'has_available' => true,
    ]);
});

it('returns correct slot data for sponsor with some sponsored members', function () {
    $sponsor = Sponsor::factory()->melody()->create();
    $users = User::factory()->count(6)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    $result = GetSponsorAvailableSlots::run($sponsor);

    expect($result)->toBe([
        'total' => 10,
        'used' => 6,
        'available' => 4,
        'has_available' => true,
    ]);
});

it('returns correct slot data for sponsor with all slots filled', function () {
    $sponsor = Sponsor::factory()->harmony()->create();
    $users = User::factory()->count(5)->create();

    foreach ($users as $user) {
        $sponsor->sponsoredMembers()->attach($user->id);
    }

    $result = GetSponsorAvailableSlots::run($sponsor);

    expect($result)->toBe([
        'total' => 5,
        'used' => 5,
        'available' => 0,
        'has_available' => false,
    ]);
});

it('returns correct slot data for different tiers', function () {
    $tiers = [
        'harmony' => 5,
        'melody' => 10,
        'rhythm' => 20,
        'crescendo' => 25,
        'fundraising' => 5,
        'inKind' => 10,
    ];

    foreach ($tiers as $tier => $expectedTotal) {
        $sponsor = Sponsor::factory()->{$tier}()->create();

        $result = GetSponsorAvailableSlots::run($sponsor);

        expect($result['total'])->toBe($expectedTotal)
            ->and($result['used'])->toBe(0)
            ->and($result['available'])->toBe($expectedTotal)
            ->and($result['has_available'])->toBeTrue();
    }
});

it('returns data as an array with expected keys', function () {
    $sponsor = Sponsor::factory()->create();

    $result = GetSponsorAvailableSlots::run($sponsor);

    expect($result)->toBeArray()
        ->toHaveKeys(['total', 'used', 'available', 'has_available']);
});
