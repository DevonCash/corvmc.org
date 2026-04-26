<?php

use App\Models\User;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Support\Models\Invitation;
use CorvMC\Support\Services\InvitationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->invitationService = app(InvitationService::class);
});

describe('Event RSVP: Self-invite', function () {
    it('allows a member to RSVP to a published future event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        $invitation = $this->invitationService->invite(
            subject: $event,
            invitee: $user,
        );

        // Self-invite creates with accepted status immediately
        expect($invitation)->toBeInstanceOf(Invitation::class);
        expect($invitation->status)->toBe('accepted');
        expect($invitation->inviter_id)->toBeNull();
        expect($invitation->user_id)->toBe($user->id);
        expect($invitation->responded_at)->not->toBeNull();
    });

    it('stores the RSVP as an accepted invitation on the event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        $this->invitationService->invite(subject: $event, invitee: $user);

        expect($event->acceptedInvitations()->count())->toBe(1);
        expect($event->invitations()->count())->toBe(1);
    });
});

describe('Event RSVP: Change of mind', function () {
    it('allows declining a previously accepted RSVP', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        $invitation = $this->invitationService->invite(subject: $event, invitee: $user);
        expect($invitation->isAccepted())->toBeTrue();

        $this->invitationService->decline($invitation);

        expect($invitation->fresh()->isDeclined())->toBeTrue();
        expect($event->acceptedInvitations()->count())->toBe(0);
        expect($event->declinedInvitations()->count())->toBe(1);
    });

    it('allows re-accepting after declining', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        $invitation = $this->invitationService->invite(subject: $event, invitee: $user);
        $this->invitationService->decline($invitation);
        expect($invitation->fresh()->isDeclined())->toBeTrue();

        $this->invitationService->accept($invitation->fresh());

        expect($invitation->fresh()->isAccepted())->toBeTrue();
        expect($event->acceptedInvitations()->count())->toBe(1);
    });
});

describe('Event: acceptsInvitations', function () {
    it('accepts invitations for published future scheduled events', function () {
        $event = Event::factory()->upcoming()->published()->create();

        expect($event->acceptsInvitations())->toBeTrue();
    });

    it('rejects invitations for unpublished events', function () {
        $event = Event::factory()->upcoming()->create(['published_at' => null]);

        expect($event->acceptsInvitations())->toBeFalse();
    });

    it('rejects invitations for past events', function () {
        $event = Event::factory()->completed()->create();

        expect($event->acceptsInvitations())->toBeFalse();
    });

    it('accepts invitations for at-capacity events', function () {
        $event = Event::factory()->upcoming()->published()->create([
            'status' => EventStatus::AtCapacity,
        ]);

        expect($event->acceptsInvitations())->toBeTrue();
    });

    it('rejects invitations for cancelled events', function () {
        $event = Event::factory()->upcoming()->published()->create([
            'status' => EventStatus::Cancelled,
        ]);

        expect($event->acceptsInvitations())->toBeFalse();
    });

    it('rejects invitations for postponed events', function () {
        $event = Event::factory()->upcoming()->published()->create([
            'status' => EventStatus::Postponed,
        ]);

        expect($event->acceptsInvitations())->toBeFalse();
    });
});

describe('Event: InvitationSubject contract', function () {
    it('allows self-invite', function () {
        $event = Event::factory()->upcoming()->published()->create();

        expect($event->allowsSelfInvite())->toBeTrue();
    });

    it('returns null eligible users (any member)', function () {
        $event = Event::factory()->upcoming()->published()->create();

        expect($event->eligibleUsers())->toBeNull();
    });

    it('considers any user invitable', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        expect($event->isInvitable($user))->toBeTrue();
    });

    it('rejects duplicate RSVPs with a friendly error', function () {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->published()->create();

        $this->invitationService->invite(subject: $event, invitee: $user);

        expect(fn () => $this->invitationService->invite(subject: $event, invitee: $user))
            ->toThrow(\InvalidArgumentException::class, 'not eligible');
    });

    it('throws when RSVPing to an event that does not accept invitations', function () {
        $user = User::factory()->create();
        $event = Event::factory()->completed()->create();

        expect(fn () => $this->invitationService->invite(subject: $event, invitee: $user))
            ->toThrow(\InvalidArgumentException::class, 'not currently accepting invitations');
    });
});

describe('Event: invitation relationships', function () {
    it('provides pending, accepted, and declined scopes', function () {
        $event = Event::factory()->upcoming()->published()->create();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // User 1 RSVPs (accepted via self-invite)
        $this->invitationService->invite(subject: $event, invitee: $user1);

        // User 2 RSVPs then declines
        $inv2 = $this->invitationService->invite(subject: $event, invitee: $user2);
        $this->invitationService->decline($inv2);

        // User 3 is invited by organizer (pending)
        $organizer = User::factory()->create();
        $this->invitationService->invite(subject: $event, invitee: $user3, inviter: $organizer);

        expect($event->acceptedInvitations()->count())->toBe(1);
        expect($event->declinedInvitations()->count())->toBe(1);
        expect($event->pendingInvitations()->count())->toBe(1);
        expect($event->invitations()->count())->toBe(3);
    });
});
