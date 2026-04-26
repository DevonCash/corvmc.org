<?php

use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Support\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    // Get a mock panel for testing
    $this->bandPanel = Filament::getPanel('band');
});

it('user can get tenants for band panel when owner', function () {
    $user = User::factory()->create();
    // BandFactory automatically creates the owner as an active member
    $band = Band::factory()->create(['owner_id' => $user->id]);

    $tenants = $user->getTenants($this->bandPanel);

    expect($tenants)->toHaveCount(1)
        ->and($tenants->first()->id)->toBe($band->id);
});

it('user can get multiple band tenants', function () {
    $user = User::factory()->create();

    // BandFactory automatically creates the owner as an active member
    Band::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha Band']);
    Band::factory()->create(['owner_id' => $user->id, 'name' => 'Beta Band']);

    $tenants = $user->getTenants($this->bandPanel);

    expect($tenants)->toHaveCount(2);
});

it('user cannot see invited bands in tenant list', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    // Create band with different owner so user isn't auto-added
    $band = Band::factory()->create(['owner_id' => $otherUser->id]);

    // Create a pending invitation (not a membership)
    Invitation::factory()->create([
        'user_id' => $user->id,
        'invitable_type' => 'band',
        'invitable_id' => $band->id,
        'inviter_id' => $otherUser->id,
        'status' => 'pending',
    ]);

    $tenants = $user->getTenants($this->bandPanel);

    expect($tenants)->toHaveCount(0);
});

it('user can access band tenant they own', function () {
    $user = User::factory()->create();
    $band = Band::factory()->create(['owner_id' => $user->id]);

    expect($user->canAccessTenant($band))->toBeTrue();
});

it('user can access band tenant they are active member of', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $band = Band::factory()->create(['owner_id' => $otherUser->id]);
    BandMember::factory()->create([
        'band_profile_id' => $band->id,
        'user_id' => $user->id,
    ]);

    expect($user->canAccessTenant($band))->toBeTrue();
});

it('user cannot access band tenant they are not member of', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    // Create band with different owner
    $band = Band::factory()->create(['owner_id' => $otherUser->id]);

    expect($user->canAccessTenant($band))->toBeFalse();
});

it('user can access band tenant with pending invitation for acceptance page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    // Create band with different owner
    $band = Band::factory()->create(['owner_id' => $otherUser->id]);

    // Create a pending invitation
    Invitation::factory()->create([
        'user_id' => $user->id,
        'invitable_type' => 'band',
        'invitable_id' => $band->id,
        'inviter_id' => $otherUser->id,
        'status' => 'pending',
    ]);

    // Invited users can access the tenant (but middleware restricts to acceptance page only)
    expect($user->canAccessTenant($band))->toBeTrue();
});

it('tenants are returned ordered by name', function () {
    $user = User::factory()->create();

    // BandFactory automatically creates the owner as an active member
    Band::factory()->create(['owner_id' => $user->id, 'name' => 'Zephyr']);
    Band::factory()->create(['owner_id' => $user->id, 'name' => 'Acoustic']);

    $tenants = $user->getTenants($this->bandPanel);

    expect($tenants->first()->name)->toBe('Acoustic')
        ->and($tenants->last()->name)->toBe('Zephyr');
});

it('returns empty collection for non-band panel', function () {
    $user = User::factory()->create();
    Band::factory()->create(['owner_id' => $user->id]);

    $memberPanel = Filament::getPanel('member');
    $tenants = $user->getTenants($memberPanel);

    expect($tenants)->toHaveCount(0);
});
