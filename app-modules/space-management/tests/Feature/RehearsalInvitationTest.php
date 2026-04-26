<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\Support\Models\Invitation;
use CorvMC\Support\Services\InvitationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->invitationService = app(InvitationService::class);
});

/**
 * Create an upcoming reservation with explicit business-hours times
 * to satisfy WithinBusinessHours(9, 22) and after:now validation rules.
 */
function createUpcomingReservation(array $attributes = []): RehearsalReservation
{
    $offset = random_int(0, 300); // avoid overlap between tests
    $start = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0)->addMinutes($offset);
    $end = $start->copy()->addHour();

    return RehearsalReservation::factory()->create(array_merge([
        'reserved_at' => $start,
        'reserved_until' => $end,
        'status' => Confirmed::class,
    ], $attributes));
}

/**
 * Create a past reservation, bypassing watson/validating's after:now rule.
 */
function createPastReservation(array $attributes = []): RehearsalReservation
{
    $start = Carbon::yesterday()->setHour(14)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    $reservation = RehearsalReservation::factory()->make(array_merge([
        'reserved_at' => $start,
        'reserved_until' => $end,
        'status' => Confirmed::class,
    ], $attributes));

    $reservation->forceSave();

    return $reservation->fresh();
}

describe('Rehearsal attendance: inviting members', function () {
    it('allows inviting a member to a future rehearsal', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();

        $reservation = createUpcomingReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = $this->invitationService->invite(
            subject: $reservation,
            invitee: $invitee,
            inviter: $booker,
        );

        expect($invitation)->toBeInstanceOf(Invitation::class);
        expect($invitation->status)->toBe('pending');
        expect($invitation->inviter_id)->toBe($booker->id);
        expect($invitation->user_id)->toBe($invitee->id);
    });

    it('accepts and declines attendance', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();

        $reservation = createUpcomingReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = $this->invitationService->invite(
            subject: $reservation,
            invitee: $invitee,
            inviter: $booker,
        );

        $this->invitationService->accept($invitation);
        expect($invitation->fresh()->isAccepted())->toBeTrue();

        $this->invitationService->decline($invitation->fresh());
        expect($invitation->fresh()->isDeclined())->toBeTrue();
    });

    it('prevents duplicate invitations for the same user', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();

        $reservation = createUpcomingReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $this->invitationService->invite(
            subject: $reservation,
            invitee: $invitee,
            inviter: $booker,
        );

        expect(fn () => $this->invitationService->invite(
            subject: $reservation,
            invitee: $invitee,
            inviter: $booker,
        ))->toThrow(\InvalidArgumentException::class, 'not eligible');
    });
});

describe('Rehearsal: acceptsInvitations', function () {
    it('accepts invitations for future active reservations', function () {
        $reservation = createUpcomingReservation();

        expect($reservation->acceptsInvitations())->toBeTrue();
    });

    it('rejects invitations for past reservations', function () {
        $reservation = createPastReservation();

        expect($reservation->acceptsInvitations())->toBeFalse();
    });

    it('rejects invitations for cancelled reservations', function () {
        $reservation = createUpcomingReservation([
            'status' => Cancelled::class,
        ]);

        expect($reservation->acceptsInvitations())->toBeFalse();
    });
});

describe('Rehearsal: InvitationSubject contract', function () {
    it('does not allow self-invite', function () {
        $reservation = createUpcomingReservation();

        expect($reservation->allowsSelfInvite())->toBeFalse();
    });

    it('returns null eligible users', function () {
        $reservation = createUpcomingReservation();

        expect($reservation->eligibleUsers())->toBeNull();
    });

    it('throws when inviting to a past rehearsal', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();

        $reservation = createPastReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        expect(fn () => $this->invitationService->invite(
            subject: $reservation,
            invitee: $invitee,
            inviter: $booker,
        ))->toThrow(\InvalidArgumentException::class, 'not currently accepting invitations');
    });
});

describe('Rehearsal: invitation relationships', function () {
    it('tracks pending, accepted, and declined invitations', function () {
        $booker = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $reservation = createUpcomingReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        // Invite three members
        $inv1 = $this->invitationService->invite(subject: $reservation, invitee: $user1, inviter: $booker);
        $inv2 = $this->invitationService->invite(subject: $reservation, invitee: $user2, inviter: $booker);
        $inv3 = $this->invitationService->invite(subject: $reservation, invitee: $user3, inviter: $booker);

        // User 1 accepts, user 2 declines, user 3 stays pending
        $this->invitationService->accept($inv1);
        $this->invitationService->decline($inv2);

        expect($reservation->acceptedInvitations()->count())->toBe(1);
        expect($reservation->declinedInvitations()->count())->toBe(1);
        expect($reservation->pendingInvitations()->count())->toBe(1);
        expect($reservation->invitations()->count())->toBe(3);
    });
});
