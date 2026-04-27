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
use App\Filament\Member\Pages\Auth\Register;
use App\Models\Invitation as PlatformInvitation;
use Carbon\Carbon;
use CorvMC\Bands\Models\Band;
use CorvMC\Events\Exceptions\SchedulingConflictException;
use CorvMC\Events\Facades\EventService;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Moderation\Facades\SpamPreventionService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use CorvMC\Finance\Facades\CreditService;
use CorvMC\Finance\Facades\MemberBenefitService;
use CorvMC\Membership\Facades\BandService;
use CorvMC\Support\Models\Invitation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\Facades\ReservationService;
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

    it('allows sustaining member to create reservation with free hours discount', function () {
        // Arrange: Create sustaining member with credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Give user 16 blocks (8 hours at 30 min/block) of free time
        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);

        // Act: Create a 2-hour reservation
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
        ]);

        // Assert: Reservation created
        expect($reservation)->toBeInstanceOf(RehearsalReservation::class)
            ->and($reservation->reservable_id)->toBe($user->id)
            ->and((float) $reservation->duration)->toEqual(2.0);

        // Assert: Finance::price() applies free hours discount
        $lineItems = Finance::price([$reservation], $user);
        $baseItem = $lineItems->first(fn ($item) => $item->amount > 0);
        $discountItem = $lineItems->first(fn ($item) => $item->amount < 0);

        expect($baseItem->amount)->toBe(3000) // $15/hour × 2 hours
            ->and($discountItem)->not->toBeNull()
            ->and($discountItem->product_type)->toBe('free_hours_discount');
    });

    it('applies partial discount when credits cover less than full price', function () {
        // Arrange: Create sustaining member with limited credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // Give user only 1 block of free time
        CreditService::allocateMonthlyCredits($user, 1, CreditType::FreeHours);
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(1);

        // Act: Create a 2-hour reservation
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
        ]);

        // Assert: Partial discount — 1 block × $7.50 = $7.50 off $30
        $lineItems = Finance::price([$reservation], $user);
        $baseAmount = $lineItems->first(fn ($item) => $item->amount > 0)->amount;
        $netTotal = $lineItems->sum('amount');

        expect($baseAmount)->toBe(3000) // $30
            ->and($netTotal)->toBe(2250); // $30 - $7.50 = $22.50
    });

    it('charges full price for non-sustaining members', function () {
        // Arrange: Regular member without sustaining status (no credits)
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Create 2-hour reservation
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
        ]);

        // Assert: Full price, no discount
        $lineItems = Finance::price([$reservation], $user);
        expect($lineItems)->toHaveCount(1) // base only, no discount
            ->and($lineItems->first()->amount)->toBe(3000); // $15/hour × 2 hours = $30
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
            'status' => Confirmed::class,
        ]);

        // Act: Try to create overlapping reservation
        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $overlappingStart = $startTime->copy()->addHour(); // Overlaps by 1 hour
        $overlappingEnd = $overlappingStart->copy()->addHours(2);

        // Assert: Should throw validation error
        expect(fn() => RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user2->id,
            'reserved_at' => $overlappingStart,
            'reserved_until' => $overlappingEnd,
        ]))->toThrow(\Illuminate\Validation\ValidationException::class);
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

        $event = EventService::create([
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
            'status' => Confirmed::class,
        ]);

        // Act & Assert: Event creation should fail due to conflict
        expect(fn() => EventService::create([
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
            'status' => Confirmed::class,
        ]);

        // Act: Create event at external venue (same time, different location)
        $event = EventService::create([
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

    it('detects conflicts via ReservationService', function () {
        // Arrange: Create reservation
        $user = User::factory()->create();

        $startTime = Carbon::now()->addDays(10)->setHour(19)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(3);

        RehearsalReservation::factory()->create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => Confirmed::class,
        ]);

        // Act: Check for conflicts
        $conflicts = ReservationService::getConflicts($startTime, $endTime);

        // Assert: Conflicts detected
        expect($conflicts)->not->toBeEmpty();
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
        $band = BandService::create($owner, [
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

        $band = BandService::create($owner, [
            'name' => 'The Test Band',
        ]);

        $invitee = User::factory()->create();
        $invitee->assignRole('member');

        // Act: Invite member
        $invitation = BandService::inviteMember($band, $invitee, 'member', 'Drummer');

        // Assert: Invitation exists in support_invitations
        expect($invitation)->toBeInstanceOf(Invitation::class)
            ->and($invitation->isPending())->toBeTrue()
            ->and($invitation->data['role'])->toBe('member')
            ->and($invitation->data['position'])->toBe('Drummer');

        // User should not be a band member yet
        expect($band->isMember($invitee))->toBeFalse();
    });

    it('allows invited member to accept invitation', function () {
        // Arrange: Create band and invitation
        $owner = User::factory()->create();
        $owner->assignRole('member');
        $this->actingAs($owner);

        $band = BandService::create($owner, ['name' => 'The Test Band']);

        $invitee = User::factory()->create();
        $invitee->assignRole('member');

        $invitation = BandService::inviteMember($band, $invitee, 'member');

        // Act: Accept invitation
        BandService::acceptInvitation($invitation);

        // Assert: Member is now active
        $membership = $band->memberships()->where('user_id', $invitee->id)->first();
        expect($membership)->not->toBeNull()
            ->and($membership->role)->toBe('member');

        // Assert: Invitation is accepted
        expect($invitation->fresh()->isAccepted())->toBeTrue();
    });

    it('prevents duplicate invitations for same user', function () {
        // Arrange
        $owner = User::factory()->create();
        $owner->assignRole('member');
        $this->actingAs($owner);

        $band = BandService::create($owner, ['name' => 'The Test Band']);

        $member = User::factory()->create();
        $member->assignRole('member');

        // First invitation
        BandService::inviteMember($band, $member, 'member');

        // Act & Assert: Second invitation should fail (unique constraint)
        expect(fn() => BandService::inviteMember($band, $member, 'member'))
            ->toThrow(\Exception::class);
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
        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);

        // Assert: User has credits
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('resets credits on new month (no rollover for free hours)', function () {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        // First allocation
        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);

        // Use some credits
        $user->deductCredit(8, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(8);

        // Act: New month allocation
        $this->travel(1)->month();
        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);

        // Assert: Reset to 16, not 16 + 8
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(16);
    });

    it('handles mid-month subscription upgrade', function () {
        // Arrange: User with basic tier
        $user = User::factory()->create();
        $user->assignRole('sustaining member');

        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);

        // Use 4 blocks, have 12 left
        $user->deductCredit(4, CreditType::FreeHours, 'test_usage');
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(12);

        // Act: Upgrade to higher tier (32 blocks) mid-month
        CreditService::allocateMonthlyCredits($user, 32, CreditType::FreeHours);

        // Assert: Gets tier delta added (32 - 16 = 16 extra), so 12 + 16 = 28
        expect($user->getCreditBalance(CreditType::FreeHours))->toBe(28);
    });

    it('deducts credits when order is committed for a reservation', function () {
        // Arrange: Sustaining member with credits
        $user = User::factory()->create();
        $user->assignRole('sustaining member');
        CreditService::allocateMonthlyCredits($user, 16, CreditType::FreeHours);

        // Act: Create 2-hour reservation and commit an Order
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0)->setSecond(0);
        $endTime = $startTime->copy()->addHours(2);

        $reservation = RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
        ]);

        // Credits not deducted yet — deduction happens at commit time
        expect($user->fresh()->getCreditBalance(CreditType::FreeHours))->toBe(16);

        // Create and commit an Order with discount
        $order = \CorvMC\Finance\Models\Order::create(['user_id' => $user->id, 'total_amount' => 0]);
        $lineItems = Finance::price([$reservation], $user);
        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }
        $netTotal = $lineItems->sum('amount');
        $order->update(['total_amount' => $netTotal]);
        Finance::commit($order->fresh(), $netTotal > 0 ? ['cash' => $netTotal] : []);

        // Assert: Credits deducted after commit
        $newBalance = $user->fresh()->getCreditBalance(CreditType::FreeHours);
        expect($newBalance)->toBeLessThan(16);
    });
});

/*
|--------------------------------------------------------------------------
| Flow 5: New User Signup
|--------------------------------------------------------------------------
|
| Tests the full registration flow through the Filament Register page,
| including spam checking, standard signup, and invitation-based signup.
|
*/

describe('Flow 5: New User Signup', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel('member'));
        Notification::fake();
    });

    it('registers a new user through the member panel', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->andReturn([
                'is_spam' => false,
                'frequency' => 0,
                'last_seen' => null,
                'confidence' => 0,
                'source' => 'stopforumspam',
            ]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'New Member',
                'email' => 'newmember@example.com',
                'password' => 'password123',
                'passwordConfirmation' => 'password123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'newmember@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('New Member');
    });

    it('blocks registration for spam-flagged emails', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->andReturn([
                'is_spam' => true,
                'frequency' => 42,
                'last_seen' => '2026-04-01',
                'confidence' => 90,
                'source' => 'stopforumspam',
            ]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Spammy User',
                'email' => 'spammer@example.com',
                'password' => 'password123',
                'passwordConfirmation' => 'password123',
            ])
            ->call('register')
            ->assertHasFormErrors(['email']);

        expect(User::where('email', 'spammer@example.com')->exists())->toBeFalse();
    });

    it('registers a user from a valid invitation and marks it used', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->andReturn([
                'is_spam' => false,
                'frequency' => 0,
                'last_seen' => null,
                'confidence' => 0,
                'source' => 'stopforumspam',
            ]);

        $inviter = User::factory()->create();

        $invitation = PlatformInvitation::withoutGlobalScopes()->create([
            'inviter_id' => $inviter->id,
            'email' => 'invited@example.com',
            'token' => 'test-invitation-token',
            'expires_at' => now()->addDays(7),
            'last_sent_at' => now(),
            'data' => [],
        ]);

        Livewire::withQueryParams(['invitation' => 'test-invitation-token'])
            ->test(Register::class)
            ->fillForm([
                'name' => 'Invited User',
                'email' => 'invited@example.com',
                'password' => 'password123',
                'passwordConfirmation' => 'password123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'invited@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Invited User');

        // Invitation should be marked as used (bypass global scopes since 'unused' scope filters used_at)
        $freshInvitation = PlatformInvitation::withoutGlobalScopes()->find($invitation->id);
        expect($freshInvitation->isUsed())->toBeTrue();
    });

    it('prevents duplicate email registration', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->andReturn([
                'is_spam' => false,
                'frequency' => 0,
                'last_seen' => null,
                'confidence' => 0,
                'source' => 'stopforumspam',
            ]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Duplicate User',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'passwordConfirmation' => 'password123',
            ])
            ->call('register')
            ->assertHasFormErrors(['email']);
    });
});
