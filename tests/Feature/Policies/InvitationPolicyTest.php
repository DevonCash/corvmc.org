<?php

use App\Models\User;
use App\Policies\InvitationPolicy;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Support\Models\Invitation;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new InvitationPolicy();
});

describe('viewAny', function () {
    it('allows any authenticated user', function () {
        $user = User::factory()->create();

        expect($this->policy->viewAny($user))->toBeTrue();
    });
});

describe('view', function () {
    it('allows the invitee to view their invitation', function () {
        $invitee = User::factory()->create();
        $invitation = Invitation::factory()->create(['user_id' => $invitee->id]);

        expect($this->policy->view($invitee, $invitation))->toBeTrue();
    });

    it('allows the inviter to view the invitation they sent', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create(['inviter_id' => $inviter->id]);

        expect($this->policy->view($inviter, $invitation))->toBeTrue();
    });

    it('denies unrelated users from viewing', function () {
        $stranger = User::factory()->create();
        $invitation = Invitation::factory()->create();

        expect($this->policy->view($stranger, $invitation))->toBeFalse();
    });
});

describe('respond', function () {
    it('allows the invitee to respond to a pending invitation', function () {
        $invitee = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'user_id' => $invitee->id,
            'status' => 'pending',
        ]);

        expect($this->policy->respond($invitee, $invitation))->toBeTrue();
    });

    it('denies non-invitee from responding', function () {
        $stranger = User::factory()->create();
        $invitation = Invitation::factory()->create(['status' => 'pending']);

        expect($this->policy->respond($stranger, $invitation))->toBeFalse();
    });

    it('denies responding to an already accepted invitation', function () {
        $invitee = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'user_id' => $invitee->id,
            'status' => 'accepted',
        ]);

        expect($this->policy->respond($invitee, $invitation))->toBeFalse();
    });

    it('denies responding to a declined invitation', function () {
        $invitee = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'user_id' => $invitee->id,
            'status' => 'declined',
        ]);

        expect($this->policy->respond($invitee, $invitation))->toBeFalse();
    });
});

describe('retract', function () {
    it('allows the inviter to retract a pending invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'status' => 'pending',
        ]);

        expect($this->policy->retract($inviter, $invitation))->toBeTrue();
    });

    it('allows band owner to retract a pending invitation', function () {
        $owner = User::factory()->create();
        $inviter = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'status' => 'pending',
        ]);

        expect($this->policy->retract($owner, $invitation))->toBeTrue();
    });

    it('allows band admin to retract a pending invitation', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'status' => 'pending',
        ]);

        expect($this->policy->retract($admin, $invitation))->toBeTrue();
    });

    it('denies regular band member from retracting', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $invitation = Invitation::factory()->create([
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'status' => 'pending',
        ]);

        expect($this->policy->retract($member, $invitation))->toBeFalse();
    });

    it('denies unrelated user from retracting', function () {
        $owner = User::factory()->create();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();

        $band = Band::factory()->create(['owner_id' => $owner->id]);
        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'status' => 'pending',
        ]);

        expect($this->policy->retract($stranger, $invitation))->toBeFalse();
    });

    it('denies retracting an already accepted invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'status' => 'accepted',
        ]);

        expect($this->policy->retract($inviter, $invitation))->toBeFalse();
    });

    it('denies retracting a declined invitation', function () {
        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'status' => 'declined',
        ]);

        expect($this->policy->retract($inviter, $invitation))->toBeFalse();
    });
});
