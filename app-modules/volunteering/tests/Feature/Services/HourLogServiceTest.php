<?php

use App\Models\User;
use CorvMC\Volunteering\Events\HoursApproved;
use CorvMC\Volunteering\Events\HoursSubmitted;
use CorvMC\Volunteering\Events\VolunteerCheckedIn;
use CorvMC\Volunteering\Events\VolunteerCheckedOut;
use CorvMC\Volunteering\Events\VolunteerConfirmed;
use CorvMC\Volunteering\Events\VolunteerReleased;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Approved;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Pending;
use CorvMC\Volunteering\States\HourLogState\Rejected;
use CorvMC\Volunteering\States\HourLogState\Released;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(HourLogService::class);
    $this->user = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->position = Position::factory()->create();
    $this->shift = Shift::factory()->for($this->position, 'position')->create([
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(3),
        'capacity' => 2,
    ]);
});

// =========================================================================
// Sign Up
// =========================================================================

describe('HourLogService::signUp', function () {
    it('creates an hour log in Interested status', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);

        expect($hourLog)->toBeInstanceOf(HourLog::class);
        expect($hourLog->user_id)->toBe($this->user->id);
        expect($hourLog->shift_id)->toBe($this->shift->id);
        expect($hourLog->status)->toBeInstanceOf(Interested::class);
    });

    it('rejects sign-up for a past shift', function () {
        $pastShift = Shift::factory()->for($this->position, 'position')->past()->create();

        $this->service->signUp($this->user, $pastShift);
    })->throws(InvalidArgumentException::class, 'already started');

    it('rejects sign-up when shift is full', function () {
        $shift = Shift::factory()->for($this->position, 'position')->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 1,
        ]);

        // Fill the slot
        HourLog::factory()->for($shift, 'shift')->confirmed()->create();

        $this->service->signUp($this->user, $shift);
    })->throws(RuntimeException::class, 'full');

    it('rejects duplicate active sign-up', function () {
        $this->service->signUp($this->user, $this->shift);

        $this->service->signUp($this->user, $this->shift);
    })->throws(RuntimeException::class, 'already have an active');

    it('allows re-signup after being released', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->release($hourLog, $this->staff);

        $newHourLog = $this->service->signUp($this->user, $this->shift);

        expect($newHourLog->id)->not->toBe($hourLog->id);
        expect($newHourLog->status)->toBeInstanceOf(Interested::class);
    });
});

// =========================================================================
// Confirm
// =========================================================================

describe('HourLogService::confirm', function () {
    it('transitions Interested to Confirmed and fires event', function () {
        Event::fake([VolunteerConfirmed::class]);

        $hourLog = $this->service->signUp($this->user, $this->shift);
        $confirmed = $this->service->confirm($hourLog, $this->staff);

        expect($confirmed->status)->toBeInstanceOf(Confirmed::class);
        expect($confirmed->reviewed_by)->toBe($this->staff->id);

        Event::assertDispatched(VolunteerConfirmed::class);
    });
});

// =========================================================================
// Release
// =========================================================================

describe('HourLogService::release', function () {
    it('releases from Interested and fires event', function () {
        Event::fake([VolunteerReleased::class]);

        $hourLog = $this->service->signUp($this->user, $this->shift);
        $released = $this->service->release($hourLog, $this->staff);

        expect($released->status)->toBeInstanceOf(Released::class);
        expect($released->reviewed_by)->toBe($this->staff->id);

        Event::assertDispatched(VolunteerReleased::class);
    });

    it('releases from Confirmed', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->confirm($hourLog, $this->staff);
        $released = $this->service->release($hourLog->fresh(), $this->staff);

        expect($released->status)->toBeInstanceOf(Released::class);
    });

    it('releases from CheckedIn', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->confirm($hourLog, $this->staff);
        $this->service->checkIn($hourLog->fresh());
        $released = $this->service->release($hourLog->fresh(), $this->staff);

        expect($released->status)->toBeInstanceOf(Released::class);
    });
});

// =========================================================================
// Check In
// =========================================================================

describe('HourLogService::checkIn', function () {
    it('transitions Confirmed to CheckedIn with started_at and fires event', function () {
        Event::fake([VolunteerCheckedIn::class]);

        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->confirm($hourLog, $this->staff);
        $checkedIn = $this->service->checkIn($hourLog->fresh());

        expect($checkedIn->status)->toBeInstanceOf(CheckedIn::class);
        expect($checkedIn->started_at)->not->toBeNull();

        Event::assertDispatched(VolunteerCheckedIn::class);
    });
});

// =========================================================================
// Walk In
// =========================================================================

describe('HourLogService::walkIn', function () {
    it('creates an hour log directly in CheckedIn status', function () {
        Event::fake([VolunteerCheckedIn::class]);

        $hourLog = $this->service->walkIn($this->user, $this->shift);

        expect($hourLog->status)->toBeInstanceOf(CheckedIn::class);
        expect($hourLog->started_at)->not->toBeNull();
        expect($hourLog->shift_id)->toBe($this->shift->id);

        Event::assertDispatched(VolunteerCheckedIn::class);
    });

    it('rejects walk-in when shift is full', function () {
        $shift = Shift::factory()->for($this->position, 'position')->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 1,
        ]);

        HourLog::factory()->for($shift, 'shift')->confirmed()->create();

        $this->service->walkIn($this->user, $shift);
    })->throws(RuntimeException::class, 'full');

    it('rejects duplicate walk-in', function () {
        $this->service->walkIn($this->user, $this->shift);

        $this->service->walkIn($this->user, $this->shift);
    })->throws(RuntimeException::class, 'already has an active');
});

// =========================================================================
// Check Out
// =========================================================================

describe('HourLogService::checkOut', function () {
    it('transitions CheckedIn to CheckedOut with ended_at and fires event', function () {
        Event::fake([VolunteerCheckedOut::class]);

        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->confirm($hourLog, $this->staff);
        $this->service->checkIn($hourLog->fresh());
        $checkedOut = $this->service->checkOut($hourLog->fresh());

        expect($checkedOut->status)->toBeInstanceOf(CheckedOut::class);
        expect($checkedOut->ended_at)->not->toBeNull();
        expect($checkedOut->minutes)->toBeGreaterThanOrEqual(0);

        Event::assertDispatched(VolunteerCheckedOut::class);
    });

    it('propagates tags from shift and position on checkout', function () {
        $this->position->attachTag('sound');
        $this->shift->attachTag('friday-show');

        $hourLog = $this->service->signUp($this->user, $this->shift);
        $this->service->confirm($hourLog, $this->staff);
        $this->service->checkIn($hourLog->fresh());
        $checkedOut = $this->service->checkOut($hourLog->fresh());

        $tagNames = $checkedOut->tags->pluck('name')->sort()->values()->all();
        expect($tagNames)->toBe(['friday-show', 'sound']);
    });
});

// =========================================================================
// Submit Hours (self-reported)
// =========================================================================

describe('HourLogService::submitHours', function () {
    it('creates a self-reported hour log in Pending status', function () {
        Event::fake([HoursSubmitted::class]);

        $hourLog = $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
            'notes' => 'Worked on grant application',
        ]);

        expect($hourLog->status)->toBeInstanceOf(Pending::class);
        expect($hourLog->position_id)->toBe($this->position->id);
        expect($hourLog->shift_id)->toBeNull();
        expect($hourLog->notes)->toBe('Worked on grant application');
        expect($hourLog->minutes)->toBe(120);

        Event::assertDispatched(HoursSubmitted::class);
    });

    it('rejects when started_at >= ended_at', function () {
        $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subHours(3),
        ]);
    })->throws(InvalidArgumentException::class, 'started_at must be before ended_at');

    it('rejects when ended_at is in the future', function () {
        $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHour(),
            'ended_at' => now()->addHour(),
        ]);
    })->throws(InvalidArgumentException::class, 'ended_at must be in the past');

    it('rejects a soft-deleted position', function () {
        $this->position->delete();

        $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);
    })->throws(InvalidArgumentException::class, 'Position does not exist');
});

// =========================================================================
// Approve
// =========================================================================

describe('HourLogService::approve', function () {
    it('transitions Pending to Approved and fires event', function () {
        Event::fake([HoursSubmitted::class, HoursApproved::class]);

        $hourLog = $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);

        $approved = $this->service->approve($hourLog, $this->staff);

        expect($approved->status)->toBeInstanceOf(Approved::class);
        expect($approved->reviewed_by)->toBe($this->staff->id);

        Event::assertDispatched(HoursApproved::class);
    });

    it('propagates position tags on approve', function () {
        Event::fake([HoursSubmitted::class, HoursApproved::class]);

        $this->position->attachTag('grants');

        $hourLog = $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);

        $approved = $this->service->approve($hourLog, $this->staff, ['q1-2026']);

        $tagNames = $approved->tags->pluck('name')->sort()->values()->all();
        expect($tagNames)->toBe(['grants', 'q1-2026']);
    });
});

// =========================================================================
// Reject
// =========================================================================

describe('HourLogService::reject', function () {
    it('transitions Pending to Rejected with notes', function () {
        Event::fake([HoursSubmitted::class]);

        $hourLog = $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);

        $rejected = $this->service->reject($hourLog, $this->staff, 'Could not verify these hours');

        expect($rejected->status)->toBeInstanceOf(Rejected::class);
        expect($rejected->reviewed_by)->toBe($this->staff->id);
        expect($rejected->notes)->toBe('Could not verify these hours');
    });
});

// =========================================================================
// Invalid state transitions
// =========================================================================

describe('invalid state transitions', function () {
    it('cannot check out an Interested hour log', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);

        $this->service->checkOut($hourLog);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);

    it('cannot check in an Interested hour log', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);

        $this->service->checkIn($hourLog);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);

    it('cannot approve a shift-based hour log', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);

        $this->service->approve($hourLog, $this->staff);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);

    it('cannot confirm a self-reported hour log', function () {
        Event::fake([HoursSubmitted::class]);

        $hourLog = $this->service->submitHours($this->user, [
            'position_id' => $this->position->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);

        $this->service->confirm($hourLog, $this->staff);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);

    it('cannot confirm an already-checked-out hour log', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);
        $hourLog = $this->service->confirm($hourLog, $this->staff);
        $hourLog = $this->service->checkIn($hourLog);
        $hourLog = $this->service->checkOut($hourLog);

        $this->service->confirm($hourLog, $this->staff);
    })->throws(\Spatie\ModelStates\Exceptions\CouldNotPerformTransition::class);
});

// =========================================================================
// Full shift lifecycle (integration)
// =========================================================================

describe('full shift lifecycle', function () {
    it('goes from sign-up through checkout', function () {
        $hourLog = $this->service->signUp($this->user, $this->shift);
        expect($hourLog->status)->toBeInstanceOf(Interested::class);

        $hourLog = $this->service->confirm($hourLog, $this->staff);
        expect($hourLog->status)->toBeInstanceOf(Confirmed::class);

        $hourLog = $this->service->checkIn($hourLog);
        expect($hourLog->status)->toBeInstanceOf(CheckedIn::class);
        expect($hourLog->started_at)->not->toBeNull();

        $hourLog = $this->service->checkOut($hourLog);
        expect($hourLog->status)->toBeInstanceOf(CheckedOut::class);
        expect($hourLog->ended_at)->not->toBeNull();
        expect($hourLog->countsTowardReporting())->toBeTrue();
    });
});
