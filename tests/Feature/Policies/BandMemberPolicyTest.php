<?php

use App\Models\User;
use App\Policies\BandMemberPolicy;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new BandMemberPolicy();
});

describe('viewAny', function () {
    it('allows any authenticated user to view band members list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('denies viewing individual band member records', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->view($owner, $bandMember))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create band members', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('update', function () {
    it('allows owner to update band member', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->update($owner, $bandMember))->toBeTrue();
    });

    it('allows admin member to update other band members', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Add admin
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        // Another member to update
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->update($admin, $bandMember))->toBeTrue();
    });

    it('allows directory moderator to update any band member', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->update($moderator, $bandMember))->toBeTrue();
    });

    it('denies regular member from updating other band members', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        // Another member
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->update($member, $bandMember))->toBeFalse();
    });

    it('denies outsiders from updating band members', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->update($outsider, $bandMember))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows owner to delete active band member', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->delete($owner, $bandMember))->toBeTrue();
    });

    it('allows admin member to delete other active band members', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Add admin
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        // Another member to delete
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->delete($admin, $bandMember))->toBeTrue();
    });

    it('denies regular member from deleting other band members', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        // Another member
        $bandMember = BandMember::factory()->create([
            'band_profile_id' => $band->id,
        ]);

        expect($this->policy->delete($member, $bandMember))->toBeFalse();
    });
});

// Note: cancel, accept, and decline policy methods have moved to InvitationPolicy
// and will be tested there (Epic 6).
