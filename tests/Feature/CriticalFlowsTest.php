<?php

/**
 * Critical Flow Tests
 *
 * These tests verify the core business flows continue working during
 * the module migration. They test external behavior through Actions,
 * not internal structure, so they survive namespace changes.
 *
 * Run these after each module migration phase.
 *
 * Run with: php artisan test --group=critical
 */

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Bands\Models\Band;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Exceptions\SchedulingConflictException;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\Finance\Actions\Credits\AllocateMonthlyCredits;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Membership\Actions\Bands\AcceptBandInvitation;
use CorvMC\Membership\Actions\Bands\AddBandMember;
use CorvMC\Membership\Actions\Bands\CreateBand;
use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;
use CorvMC\SpaceManagement\Actions\Reservations\CreateReservation;
use CorvMC\SpaceManagement\Actions\Reservations\GetAllConflicts;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Support\Facades\Notification;

uses()->group('critical', 'smoke');

/*
|--------------------------------------------------------------------------
| Flow 1: Create Reservation with Credits
|--------------------------------------------------------------------------
|
| Tests that sustaining members can create reservations using their
| free hours, and that credits are properly deducted.
|
*/

describe('Flow 1: Create Reservation with Credits', function () {
    beforeEach(function () {
        // Create CMC venue for reservations
        Venue::create([
            'name' => 'CMC Practice Space',
            'is_cmc' => true,
            'address' => '420 NW 5th St',
            'city' => 'Corvallis',
            'state' => 'OR',
        ]);

        // Fake notifications to avoid external calls
        Notification::fake();
    });

    it('allows sustaining member to create reservation using free hours', function () {
        // Arrange: Create sustaining member with credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Give user 16 blocks (8 hours at 30 min/block) of free time
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);

        // Act: Create a 2-hour reservation (4 blocks at 30 min/block)
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Assert: Reservation created with free hours applied
        expect($reservation)->toBeInstanceOf(RehearsalReservation::class)
            ->and($reservation->reservable_id)->toBe($user->id)
            ->and((float) $reservation->hours_used)->toEqual(2.0)
            ->and((float) $reservation->free_hours_used)->toEqual(2.0)
            ->and($reservation->charge->net_amount->isZero())->toBeTrue();

        // Assert: Credits were deducted (2 hours = 4 blocks at 30 min/block)
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(12);
    });

    it('charges for hours beyond free credit balance', function () {
        // Arrange: Create sustaining member with limited credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Give user only 2 blocks (1 hour at 30 min/block) of free time
        AllocateMonthlyCredits::run($user, 2, CreditType::FreeHours);

        // Act: Create a 2-hour reservation (needs 4 blocks, only has 2)
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = CreateReservation::run($user, $startTime, $endTime);

        // Assert: 1 hour free (2 blocks), 1 hour paid ($15)
        expect((float) $reservation->hours_used)->toEqual(2.0)
            ->and((float) $reservation->free_hours_used)->toEqual(1.0)
            ->and($reservation->charge->net_amount->getMinorAmount()->toInt())->toEqual(1500);

        // Assert: All credits used
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(0);
    });

    it('calculates cost correctly for non-members', function () {
        // Arrange: Regular member without sustaining status
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Calculate cost for 2-hour reservation
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $cost = CalculateReservationCost::run($user, $startTime, $endTime);

        // Assert: Full price, no free hours
        expect($cost['total_hours'])->toEqual(2.0)
            ->and($cost['free_hours'])->toEqual(0)
            ->and($cost['paid_hours'])->toEqual(2.0)
            ->and($cost['cost']->getAmount()->toFloat())->toEqual(30.0)
            ->and($cost['is_sustaining_member'])->toBeFalse();
    });

    it('prevents conflicting reservations', function () {
        // Arrange: Create existing reservation
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user1->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Act: Try to create overlapping reservation
        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $overlappingStart = $startTime->copy()->addHour(); // Overlaps by 1 hour
        $overlappingEnd = $overlappingStart->copy()->addHours(2);

        // Assert: Should throw validation error
        expect(fn () => CreateReservation::run($user2, $overlappingStart, $overlappingEnd))
            ->toThrow(\InvalidArgumentException::class);
    });
});

/*
|--------------------------------------------------------------------------
| Flow 2: Create Event with Conflict Checking
|--------------------------------------------------------------------------
|
| Tests that events at CMC venue check for conflicts with existing
| reservations and other events.
|
*/

describe('Flow 2: Create Event with Conflict Checking', function () {
    beforeEach(function () {
        // Fake notifications to avoid external calls
        Notification::fake();

        // Create CMC venue
        $this->cmcVenue = Venue::create([
            'name' => 'CMC Practice Space',
            'is_cmc' => true,
            'address' => '420 NW 5th St',
            'city' => 'Corvallis',
            'state' => 'OR',
        ]);

        // Create external venue for comparison
        $this->externalVenue = Venue::create([
            'name' => 'External Venue',
            'is_cmc' => false,
            'address' => '123 Main St',
            'city' => 'Portland',
            'state' => 'OR',
        ]);

        // Create an organizer
        $this->organizer = User::factory()->create();
        $this->organizer->assignRole('member');
    });

    it('creates event at CMC venue when no conflicts exist', function () {
        // Act: Create event at CMC venue
        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        $event = CreateEvent::run([
            'title' => 'Test Concert',
            'description' => 'A test event',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Assert: Event created successfully
        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->title)->toBe('Test Concert')
            ->and($event->venue_id)->toBe($this->cmcVenue->id);
    });

    it('prevents event creation when conflicting reservation exists', function () {
        // Arrange: Create existing reservation at the same time
        $user = User::factory()->create();
        $user->assignRole('member');

        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Act & Assert: Event creation should fail due to conflict
        expect(fn () => CreateEvent::run([
            'title' => 'Conflicting Concert',
            'description' => 'This should fail',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $this->organizer->id,
        ]))->toThrow(SchedulingConflictException::class);
    });

    it('allows event creation at external venue regardless of CMC conflicts', function () {
        // Arrange: Create reservation at CMC
        $user = User::factory()->create();
        $user->assignRole('member');

        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Act: Create event at external venue (same time, different location)
        $event = CreateEvent::run([
            'title' => 'External Venue Concert',
            'description' => 'Should succeed at external venue',
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'venue_id' => $this->externalVenue->id,
            'organizer_id' => $this->organizer->id,
        ]);

        // Assert: Event created successfully (external venue doesn't conflict)
        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->venue_id)->toBe($this->externalVenue->id);
    });

    it('detects conflicts via GetAllConflicts action', function () {
        // Arrange: Create reservation
        $user = User::factory()->create();

        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Act: Check for conflicts
        $conflicts = GetAllConflicts::run($startTime, $endTime);

        // Assert: Conflicts detected
        expect($conflicts['reservations'])->not->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| Flow 3: Create Band and Invite Member
|--------------------------------------------------------------------------
|
| Tests the full band creation and member invitation workflow.
|
*/

describe('Flow 3: Create Band and Invite Member', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('creates band with owner', function () {
        // Arrange
        $owner = User::factory()->create();
        $owner->assignRole('member');

        // Need to set the authenticated user for authorization
        $this->actingAs($owner);

        // Act: Create band
        $band = CreateBand::run([
            'name' => 'The Test Band',
            'bio' => 'A band for testing',
        ]);

        // Assert: Band created with owner
        expect($band)->toBeInstanceOf(Band::class)
            ->and($band->name)->toBe('The Test Band')
            ->and($band->owner_id)->toBe($owner->id);
    });

    it('sends invitation to new member', function () {
        // Arrange
        $owner = User::factory()->create();
        $owner->assignRole('member');
        $this->actingAs($owner);

        $band = CreateBand::run([
            'name' => 'The Test Band',
        ]);

        $invitee = User::factory()->create();
        $invitee->assignRole('member');

        // Act: Invite member
        AddBandMember::run($band, $invitee, [
            'role' => 'member',
            'position' => 'Drummer',
        ]);

        // Assert: Invitation exists
        $invitation = $band->memberships()->invited()->where('user_id', $invitee->id)->first();
        expect($invitation)->not->toBeNull()
            ->and($invitation->role)->toBe('member')
            ->and($invitation->position)->toBe('Drummer');
    });

    it('allows invited member to accept invitation', function () {
        // Arrange: Create band and invitation
        $owner = User::factory()->create();
        $owner->assignRole('member');
        $this->actingAs($owner);

        $band = CreateBand::run(['name' => 'The Test Band']);

        $invitee = User::factory()->create();
        $invitee->assignRole('member');

        AddBandMember::run($band, $invitee, ['role' => 'member']);

        // Act: Accept invitation
        AcceptBandInvitation::run($band, $invitee);

        // Assert: Member is now active
        $membership = $band->memberships()->active()->where('user_id', $invitee->id)->first();
        expect($membership)->not->toBeNull()
            ->and($membership->status)->toBe('active');

        // Assert: No longer shows as invited
        expect($band->memberships()->invited()->where('user_id', $invitee->id)->exists())->toBeFalse();
    });

    it('prevents duplicate active memberships', function () {
        // Arrange
        $owner = User::factory()->create();
        $owner->assignRole('member');
        $this->actingAs($owner);

        $band = CreateBand::run(['name' => 'The Test Band']);

        $member = User::factory()->create();
        $member->assignRole('member');

        // Add and accept first invitation
        AddBandMember::run($band, $member, ['role' => 'member']);
        AcceptBandInvitation::run($band, $member);

        // Act & Assert: Second invitation should fail
        expect(fn () => AddBandMember::run($band, $member, ['role' => 'member']))
            ->toThrow(\CorvMC\Bands\Exceptions\BandException::class);
    });
});

/*
|--------------------------------------------------------------------------
| Flow 4: Subscription Credit Allocation
|--------------------------------------------------------------------------
|
| Tests that subscribing members receive their monthly credits.
| Note: We test credit allocation, not Stripe checkout (external service).
|
*/

describe('Flow 4: Subscription Credit Allocation', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('allocates monthly credits to sustaining members', function () {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Act: Allocate monthly credits (simulates what happens after subscription)
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Assert: User has credits
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('resets credits on new month (no rollover for free hours)', function () {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // First allocation
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Use some credits
        $user->deductCredit(8, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(8);

        // Act: New month allocation
        $this->travel(1)->month();
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Assert: Reset to 16, not 16 + 8
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('handles mid-month subscription upgrade', function () {
        // Arrange: User with basic tier
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        // Use 4 blocks, have 12 left
        $user->deductCredit(4, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(12);

        // Act: Upgrade to higher tier (32 blocks) mid-month
        AllocateMonthlyCredits::run($user, 32, CreditType::FreeHours);

        // Assert: Gets tier delta added (32 - 16 = 16 extra), so 12 + 16 = 28
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(28);
    });

    it('deducts credits when reservation is created', function () {
        // Arrange: Sustaining member with credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        AllocateMonthlyCredits::run($user, 16, CreditType::FreeHours);

        Venue::create(['name' => 'CMC', 'is_cmc' => true, 'address' => '420 NW 5th St', 'city' => 'Corvallis', 'state' => 'OR']);

        // Act: Create 2-hour reservation (8 blocks)
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        CreateReservation::run($user, $startTime, $endTime);

        // Assert: Credits deducted (2 hours = 8 blocks, 30 min per block)
        // 2 hours * 60 min / 30 min per block = 4 blocks
        // Wait, let me check the config. Default is 30 min per block
        // So 2 hours = 120 min / 30 = 4 blocks
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(12);
    });
});
