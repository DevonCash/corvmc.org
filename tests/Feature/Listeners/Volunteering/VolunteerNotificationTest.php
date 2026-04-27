<?php

use App\Models\User;
use CorvMC\Volunteering\Events\HoursApproved;
use CorvMC\Volunteering\Events\HoursRejected;
use CorvMC\Volunteering\Events\HoursSubmitted;
use CorvMC\Volunteering\Events\VolunteerConfirmed;
use CorvMC\Volunteering\Events\VolunteerReleased;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Notifications\HoursReviewedNotification;
use CorvMC\Volunteering\Notifications\HoursSubmittedNotification;
use CorvMC\Volunteering\Notifications\ShiftConfirmedNotification;
use CorvMC\Volunteering\Notifications\ShiftReleasedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    Notification::fake();

    $this->volunteer = User::factory()->create();
    $this->volunteer->assignRole('member');

    $this->position = Position::factory()->create(['title' => 'Sound Engineer']);

    $this->shift = Shift::factory()
        ->for($this->position, 'position')
        ->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 2,
        ]);
});

// =========================================================================
// Shift confirmed
// =========================================================================

it('sends shift confirmed notification to the volunteer', function () {
    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($this->shift)
        ->confirmed()
        ->create();

    VolunteerConfirmed::dispatch($hourLog);

    Notification::assertSentTo($this->volunteer, ShiftConfirmedNotification::class);
});

// =========================================================================
// Shift released
// =========================================================================

it('sends shift released notification to the volunteer', function () {
    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->forShift($this->shift)
        ->create(['status' => \CorvMC\Volunteering\States\HourLogState\Released::class]);

    VolunteerReleased::dispatch($hourLog);

    Notification::assertSentTo($this->volunteer, ShiftReleasedNotification::class);
});

// =========================================================================
// Hours reviewed — approved
// =========================================================================

it('sends hours approved notification to the volunteer', function () {
    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->for($this->position, 'position')
        ->approved()
        ->create();

    HoursApproved::dispatch($hourLog);

    Notification::assertSentTo($this->volunteer, function (HoursReviewedNotification $notification) {
        return $notification->approved === true;
    });
});

// =========================================================================
// Hours reviewed — rejected
// =========================================================================

it('sends hours rejected notification to the volunteer', function () {
    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->for($this->position, 'position')
        ->selfReported()
        ->create([
            'status' => \CorvMC\Volunteering\States\HourLogState\Rejected::class,
            'notes' => 'Duplicate submission',
        ]);

    HoursRejected::dispatch($hourLog);

    Notification::assertSentTo($this->volunteer, function (HoursReviewedNotification $notification) {
        return $notification->approved === false;
    });
});

// =========================================================================
// Hours submitted — staff notification
// =========================================================================

it('sends hours submitted notification to all approvers', function () {
    $approver1 = User::factory()->create();
    $approver1->assignRole('staff');
    $approver1->givePermissionTo('volunteer.hours.approve');

    $approver2 = User::factory()->create();
    $approver2->assignRole('staff');
    $approver2->givePermissionTo('volunteer.hours.approve');

    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    HoursSubmitted::dispatch($hourLog);

    Notification::assertSentTo($approver1, HoursSubmittedNotification::class);
    Notification::assertSentTo($approver2, HoursSubmittedNotification::class);
});

it('does not send hours submitted notification to non-approvers', function () {
    $regularMember = User::factory()->create();
    $regularMember->assignRole('member');

    $hourLog = HourLog::factory()
        ->for($this->volunteer, 'user')
        ->for($this->position, 'position')
        ->pending()
        ->create();

    HoursSubmitted::dispatch($hourLog);

    Notification::assertNotSentTo($regularMember, HoursSubmittedNotification::class);
});
