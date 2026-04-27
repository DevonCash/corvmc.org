<?php

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');

    $this->moderator = User::factory()->create();
    $this->moderator->assignRole('moderator');

    $this->position = Position::factory()->create();
    $this->shift = Shift::factory()->for($this->position, 'position')->create([
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(3),
        'capacity' => 2,
    ]);
});

// =========================================================================
// Position and Shift policies
// =========================================================================

describe('PositionPolicy', function () {
    it('allows admin to manage positions', function () {
        expect(Gate::forUser($this->admin)->allows('create', Position::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $this->position))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('delete', $this->position))->toBeTrue();
    });

    it('denies member from managing positions', function () {
        expect(Gate::forUser($this->member)->allows('create', Position::class))->toBeFalse();
        expect(Gate::forUser($this->member)->allows('update', $this->position))->toBeFalse();
        expect(Gate::forUser($this->member)->allows('delete', $this->position))->toBeFalse();
    });
});

describe('ShiftPolicy', function () {
    it('allows admin to manage shifts', function () {
        expect(Gate::forUser($this->admin)->allows('create', Shift::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $this->shift))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('delete', $this->shift))->toBeTrue();
    });

    it('denies member from managing shifts', function () {
        expect(Gate::forUser($this->member)->allows('create', Shift::class))->toBeFalse();
    });
});

// =========================================================================
// HourLogPolicy: sign-up
// =========================================================================

describe('HourLogPolicy::signUp', function () {
    it('allows members to sign up', function () {
        expect(Gate::forUser($this->member)->allows('signUp', HourLog::class))->toBeTrue();
    });

    it('denies users without volunteer.signup', function () {
        $noPerms = User::factory()->create();
        expect(Gate::forUser($noPerms)->allows('signUp', HourLog::class))->toBeFalse();
    });
});

// =========================================================================
// HourLogPolicy: manage (confirm/release)
// =========================================================================

describe('HourLogPolicy::manage', function () {
    it('allows moderator to confirm/release', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->moderator)->allows('confirm', $hourLog))->toBeTrue();
        expect(Gate::forUser($this->moderator)->allows('release', $hourLog))->toBeTrue();
    });

    it('denies regular member from confirming', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->create([
            'user_id' => $this->member->id,
        ]);

        $otherMember = User::factory()->create();
        $otherMember->assignRole('member');

        expect(Gate::forUser($otherMember)->allows('confirm', $hourLog))->toBeFalse();
    });

    it('allows event organizer to manage volunteers for their event', function () {
        $organizer = User::factory()->create();
        $organizer->assignRole('member');

        $venue = Venue::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
        ]);

        $shift = Shift::factory()->for($this->position, 'position')->create([
            'event_id' => $event->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
        ]);

        $hourLog = HourLog::factory()->for($shift, 'shift')->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($organizer)->allows('confirm', $hourLog))->toBeTrue();
    });

    it('denies organizer from managing volunteers for other events', function () {
        $organizer = User::factory()->create();
        $organizer->assignRole('member');

        // Shift has no event_id — organizer has no claim
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($organizer)->allows('confirm', $hourLog))->toBeFalse();
    });
});

// =========================================================================
// HourLogPolicy: check-in
// =========================================================================

describe('HourLogPolicy::checkIn', function () {
    it('allows self-check-in when own HourLog is Confirmed', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->confirmed()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->member)->allows('checkIn', $hourLog))->toBeTrue();
    });

    it('denies self-check-in when own HourLog is Interested (not yet confirmed)', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->create([
            'user_id' => $this->member->id,
            'status' => Interested::class,
        ]);

        expect(Gate::forUser($this->member)->allows('checkIn', $hourLog))->toBeFalse();
    });

    it('allows staff with volunteer.checkin to check in others', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->confirmed()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->staff)->allows('checkIn', $hourLog))->toBeTrue();
    });

    it('denies member from checking in another member', function () {
        $otherMember = User::factory()->create();
        $otherMember->assignRole('member');

        $hourLog = HourLog::factory()->for($this->shift, 'shift')->confirmed()->create([
            'user_id' => $otherMember->id,
        ]);

        expect(Gate::forUser($this->member)->allows('checkIn', $hourLog))->toBeFalse();
    });
});

// =========================================================================
// HourLogPolicy: check-out
// =========================================================================

describe('HourLogPolicy::checkOut', function () {
    it('allows self-check-out when own HourLog is CheckedIn', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->checkedIn()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->member)->allows('checkOut', $hourLog))->toBeTrue();
    });

    it('denies self-check-out when own HourLog is Confirmed (not checked in)', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->confirmed()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->member)->allows('checkOut', $hourLog))->toBeFalse();
    });

    it('allows staff with volunteer.checkin to check out others', function () {
        $hourLog = HourLog::factory()->for($this->shift, 'shift')->checkedIn()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->staff)->allows('checkOut', $hourLog))->toBeTrue();
    });
});

// =========================================================================
// HourLogPolicy: self-reported hours
// =========================================================================

describe('HourLogPolicy::submitHours', function () {
    it('allows members to submit hours', function () {
        expect(Gate::forUser($this->member)->allows('submitHours', HourLog::class))->toBeTrue();
    });

    it('denies users without volunteer.hours.submit', function () {
        $noPerms = User::factory()->create();
        expect(Gate::forUser($noPerms)->allows('submitHours', HourLog::class))->toBeFalse();
    });
});

describe('HourLogPolicy::approve and reject', function () {
    it('allows moderator to approve hours', function () {
        $hourLog = HourLog::factory()->selfReported()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->moderator)->allows('approve', $hourLog))->toBeTrue();
        expect(Gate::forUser($this->moderator)->allows('reject', $hourLog))->toBeTrue();
    });

    it('denies member from approving hours', function () {
        $hourLog = HourLog::factory()->selfReported()->create([
            'user_id' => $this->member->id,
        ]);

        expect(Gate::forUser($this->member)->allows('approve', $hourLog))->toBeFalse();
    });
});

// =========================================================================
// HourLogPolicy: reporting
// =========================================================================

describe('HourLogPolicy::viewReport', function () {
    it('allows moderator to view reports', function () {
        expect(Gate::forUser($this->moderator)->allows('viewReport', HourLog::class))->toBeTrue();
    });

    it('denies member from viewing reports', function () {
        expect(Gate::forUser($this->member)->allows('viewReport', HourLog::class))->toBeFalse();
    });
});
