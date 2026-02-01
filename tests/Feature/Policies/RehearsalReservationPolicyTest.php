<?php

use App\Models\User;
use App\Policies\RehearsalReservationPolicy;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new RehearsalReservationPolicy();
});

describe('manage', function () {
    it('allows practice space manager to manage reservations', function () {
        $staff = User::factory()->withRole('practice space manager')->create();

        expect($this->policy->manage($staff))->toBeTrue();
    });

    it('allows admin to manage reservations', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('denies regular members from managing reservations', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies sustaining members from managing reservations', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->manage($sustainingMember))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows anyone to view reservations list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows practice space manager to view any reservation', function () {
        $staff = User::factory()->withRole('practice space manager')->create();
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->view($staff, $reservation))->toBeTrue();
    });

    it('allows owner to view their own reservation', function () {
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->view($owner, $reservation))->toBeTrue();
    });

    it('denies non-owner from viewing another users reservation', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->view($otherUser, $reservation))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create reservations', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('confirm', function () {
    it('allows practice space manager to confirm any reservation', function () {
        $staff = User::factory()->withRole('practice space manager')->create();
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->confirm($staff, $reservation))->toBeTrue();
    });

    it('allows owner to confirm their own reservation', function () {
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->confirm($owner, $reservation))->toBeTrue();
    });

    it('denies non-owner from confirming another users reservation', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->confirm($otherUser, $reservation))->toBeFalse();
    });
});

describe('cancel', function () {
    it('allows practice space manager to cancel any reservation', function () {
        $staff = User::factory()->withRole('practice space manager')->create();
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->cancel($staff, $reservation))->toBeTrue();
    });

    it('allows owner to cancel their own reservation', function () {
        $owner = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->cancel($owner, $reservation))->toBeTrue();
    });

    it('denies non-owner from cancelling another users reservation', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $reservation = RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $owner->id,
        ]);

        expect($this->policy->cancel($otherUser, $reservation))->toBeFalse();
    });
});

describe('scheduleRecurring', function () {
    it('allows sustaining members to schedule recurring reservations', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->scheduleRecurring($sustainingMember))->toBeTrue();
    });

    it('denies regular members from scheduling recurring reservations', function () {
        $member = User::factory()->create();

        expect($this->policy->scheduleRecurring($member))->toBeFalse();
    });

    it('denies practice space manager without sustaining member role from scheduling recurring reservations', function () {
        $manager = User::factory()->withRole('practice space manager')->create();

        expect($this->policy->scheduleRecurring($manager))->toBeFalse();
    });

    it('allows practice space managers who are also sustaining members to schedule recurring reservations', function () {
        $managerAndSustaining = User::factory()->withRole('practice space manager')->sustainingMember()->create();

        expect($this->policy->scheduleRecurring($managerAndSustaining))->toBeTrue();
    });
});
