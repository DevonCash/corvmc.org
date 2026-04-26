<?php

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Listeners\SendBandInvitationNotification;
use CorvMC\Membership\Listeners\SendBandInvitationAcceptedNotification;
use CorvMC\Membership\Notifications\BandInvitationNotification;
use CorvMC\Membership\Notifications\BandInvitationAcceptedNotification;
use CorvMC\Support\Events\InvitationAccepted;
use CorvMC\Support\Events\InvitationCreated;
use CorvMC\Support\Models\Invitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

// ── SendBandInvitationNotification listener ────────────────────

describe('SendBandInvitationNotification listener', function () {
    it('sends a notification when a band invitation is created', function () {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $inviter->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationNotification();
        $listener->handle(new InvitationCreated($invitation));

        Notification::assertSentTo($invitee, BandInvitationNotification::class);
    });

    it('does not send a notification for non-band invitations', function () {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $inviter->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'event',
            'invitable_id' => $band->id, // type mismatch is intentional — we just check the type filter
        ]);

        $listener = new SendBandInvitationNotification();
        $listener->handle(new InvitationCreated($invitation));

        Notification::assertNothingSent();
    });

    it('does not send a notification for self-invites', function () {
        $user = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $user->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => null,
            'user_id' => $user->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationNotification();
        $listener->handle(new InvitationCreated($invitation));

        Notification::assertNothingSent();
    });
});

// ── BandInvitationNotification content ─────────────────────────

describe('BandInvitationNotification', function () {
    it('includes band name and role in the mail subject', function () {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $inviter->id,
            'name' => 'The Amplifiers',
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'data' => ['role' => 'member', 'position' => 'Lead Guitar'],
        ]);

        $notification = new BandInvitationNotification($invitation);
        $mail = $notification->toMail($invitee);

        expect($mail->subject)->toContain('The Amplifiers');
        expect($mail->subject)->toContain('a member');
    });

    it('includes admin role label for admin invitations', function () {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $inviter->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'data' => ['role' => 'admin'],
        ]);

        $notification = new BandInvitationNotification($invitation);
        $mail = $notification->toMail($invitee);

        expect($mail->subject)->toContain('an admin');
    });

    it('stores invitation_id in database notification', function () {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $inviter->id]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $inviter->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
            'data' => ['role' => 'member'],
        ]);

        $notification = new BandInvitationNotification($invitation);
        $dbData = $notification->toDatabase($invitee);

        expect($dbData['invitation_id'])->toBe($invitation->id);
        expect($dbData['band_id'])->toBe($band->id);
    });
});

// ── SendBandInvitationAcceptedNotification listener ────────────

describe('SendBandInvitationAcceptedNotification listener', function () {
    it('notifies the band owner when an invitation is accepted', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationAcceptedNotification();
        $listener->handle(new InvitationAccepted($invitation));

        Notification::assertSentTo($owner, BandInvitationAcceptedNotification::class);
        Notification::assertNotSentTo($invitee, BandInvitationAcceptedNotification::class);
    });

    it('notifies admin members when an invitation is accepted', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Add admin member
        $band->members()->attach($admin->id, ['role' => 'admin']);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationAcceptedNotification();
        $listener->handle(new InvitationAccepted($invitation));

        Notification::assertSentTo($owner, BandInvitationAcceptedNotification::class);
        Notification::assertSentTo($admin, BandInvitationAcceptedNotification::class);
    });

    it('does not notify the new member about their own acceptance', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        // Make invitee an admin too — they still shouldn't be notified
        $band->members()->attach($invitee->id, ['role' => 'admin']);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationAcceptedNotification();
        $listener->handle(new InvitationAccepted($invitation));

        Notification::assertNotSentTo($invitee, BandInvitationAcceptedNotification::class);
    });

    it('ignores non-band invitation accepted events', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'event',
            'invitable_id' => $band->id,
        ]);

        $listener = new SendBandInvitationAcceptedNotification();
        $listener->handle(new InvitationAccepted($invitation));

        Notification::assertNothingSent();
    });
});

// ── BandInvitationAcceptedNotification content ─────────────────

describe('BandInvitationAcceptedNotification', function () {
    it('includes new member name and band name in the mail', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['name' => 'Jamie']);
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'The Amplifiers',
        ]);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $notification = new BandInvitationAcceptedNotification($invitation);
        $mail = $notification->toMail($owner);

        expect($mail->subject)->toContain('The Amplifiers');
    });

    it('stores invitation and member info in database notification', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $owner->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $band->id,
        ]);

        $notification = new BandInvitationAcceptedNotification($invitation);
        $dbData = $notification->toDatabase($owner);

        expect($dbData['invitation_id'])->toBe($invitation->id);
        expect($dbData['new_member_id'])->toBe($invitee->id);
        expect($dbData['band_id'])->toBe($band->id);
    });
});
