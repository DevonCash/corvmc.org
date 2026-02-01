<?php

use App\Models\User;
use App\Policies\RecurringSeriesPolicy;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new RecurringSeriesPolicy();
});

function createRecurringSeries(User $user): RecurringSeries
{
    return RecurringSeries::create([
        'user_id' => $user->id,
        'recurable_type' => 'rehearsal_reservation',
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=TU',
        'start_time' => '19:00:00',
        'end_time' => '21:00:00',
        'series_start_date' => now()->addWeek(),
        'series_end_date' => now()->addMonths(3),
        'max_advance_days' => 14,
        'status' => RecurringSeriesStatus::ACTIVE,
    ]);
}

describe('manage', function () {
    it('allows practice space manager to manage recurring series', function () {
        $staff = User::factory()->withRole('practice space manager')->create();

        expect($this->policy->manage($staff))->toBeTrue();
    });

    it('denies regular members from managing recurring series', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies sustaining members from managing recurring series', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->manage($sustainingMember))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows anyone to view recurring series list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows practice space manager to view any recurring series', function () {
        $staff = User::factory()->withRole('practice space manager')->create();
        $owner = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->view($staff, $series))->toBeTrue();
    });

    it('allows owner to view their own recurring series', function () {
        $owner = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->view($owner, $series))->toBeTrue();
    });

    it('denies non-owner from viewing another users recurring series', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->view($otherUser, $series))->toBeFalse();
    });
});

describe('create', function () {
    it('returns false when no recurrable type is provided', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member, null))->toBeFalse();
    });

    it('delegates to RehearsalReservation scheduleRecurring policy for reservation series', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->create($sustainingMember, RehearsalReservation::class))->toBeTrue();
    });

    it('denies regular members from creating recurring reservation series', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member, RehearsalReservation::class))->toBeFalse();
    });
});

describe('cancel', function () {
    it('allows practice space manager to cancel any recurring series', function () {
        $staff = User::factory()->withRole('practice space manager')->create();
        $owner = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->cancel($staff, $series))->toBeTrue();
    });

    it('allows owner to cancel their own recurring series', function () {
        $owner = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->cancel($owner, $series))->toBeTrue();
    });

    it('denies non-owner from cancelling another users recurring series', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $series = createRecurringSeries($owner);

        expect($this->policy->cancel($otherUser, $series))->toBeFalse();
    });
});
