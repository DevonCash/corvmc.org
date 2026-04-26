<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Console\SendRehearsalRemindersCommand;
use CorvMC\SpaceManagement\Listeners\SendRehearsalAttendanceNotification;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Notifications\RehearsalAttendanceRequestedNotification;
use CorvMC\SpaceManagement\Notifications\RehearsalReminderNotification;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\Support\Events\InvitationCreated;
use CorvMC\Support\Models\Invitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/**
 * Create an upcoming reservation with explicit business-hours times.
 */
function createNotificationReservation(array $attributes = []): RehearsalReservation
{
    $offset = random_int(0, 300);
    $start = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0)->addMinutes($offset);
    $end = $start->copy()->addHour();

    return RehearsalReservation::factory()->create(array_merge([
        'reserved_at' => $start,
        'reserved_until' => $end,
        'status' => Confirmed::class,
    ], $attributes));
}

// ── SendRehearsalAttendanceNotification listener ───────────────

describe('SendRehearsalAttendanceNotification listener', function () {
    it('sends attendance notification for rehearsal invitations', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
        ]);

        $listener = new SendRehearsalAttendanceNotification();
        $listener->handle(new InvitationCreated($invitation));

        Notification::assertSentTo($invitee, RehearsalAttendanceRequestedNotification::class);
    });

    it('does not send for non-rehearsal invitations', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'band',
            'invitable_id' => $reservation->id,
        ]);

        $listener = new SendRehearsalAttendanceNotification();
        $listener->handle(new InvitationCreated($invitation));

        Notification::assertNothingSent();
    });
});

// ── RehearsalAttendanceRequestedNotification content ───────────

describe('RehearsalAttendanceRequestedNotification', function () {
    it('includes reservation date in the mail subject', function () {
        $booker = User::factory()->create(['name' => 'Alex']);
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
        ]);

        $notification = new RehearsalAttendanceRequestedNotification($invitation);
        $mail = $notification->toMail($invitee);

        expect($mail->subject)->toContain('Rehearsal on');
        expect($mail->subject)->toContain('can you make it?');
    });

    it('stores invitation and reservation IDs in database notification', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
        ]);

        $notification = new RehearsalAttendanceRequestedNotification($invitation);
        $dbData = $notification->toDatabase($invitee);

        expect($dbData['invitation_id'])->toBe($invitation->id);
        expect($dbData['reservation_id'])->toBe($reservation->id);
        expect($dbData['icon'])->toBe('tabler-metronome');
    });
});

// ── SendRehearsalRemindersCommand ──────────────────────────────

describe('SendRehearsalRemindersCommand', function () {
    it('sends reminders for pending invitations 24 hours before rehearsal', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();

        // Create a reservation ~24 hours from now (within the 23-25 hour window)
        $start = now()->addHours(24)->setSecond(0);
        // Ensure it falls within business hours by setting to 10 AM tomorrow
        $start = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);

        // We need to make "now" such that the reservation is 23-25 hours away
        // Instead, let's create the reservation and travel time
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
            'status' => 'pending',
        ]);

        // Travel to 24 hours before the reservation
        $this->travelTo($reservation->reserved_at->copy()->subHours(24));

        $this->artisan('rehearsals:send-reminders')
            ->assertSuccessful();

        Notification::assertSentTo($invitee, RehearsalReminderNotification::class);
    });

    it('stamps reminded_at and does not re-send', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
            'status' => 'pending',
        ]);

        // Travel to 24 hours before
        $this->travelTo($reservation->reserved_at->copy()->subHours(24));

        // First run — should send
        $this->artisan('rehearsals:send-reminders')->assertSuccessful();
        Notification::assertSentTo($invitee, RehearsalReminderNotification::class);

        // Check reminded_at was stamped
        $invitation->refresh();
        expect($invitation->data['reminded_at'])->not()->toBeNull();

        // Clear fake and run again — should not re-send
        Notification::fake();
        $this->artisan('rehearsals:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    });

    it('does not send reminders for accepted invitations', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->accepted()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
        ]);

        $this->travelTo($reservation->reserved_at->copy()->subHours(24));

        $this->artisan('rehearsals:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    });

    it('does not send reminders for rehearsals more than 25 hours away', function () {
        $booker = User::factory()->create();
        $invitee = User::factory()->create();
        $reservation = createNotificationReservation([
            'reservable_type' => 'user',
            'reservable_id' => $booker->id,
        ]);

        $invitation = Invitation::factory()->create([
            'inviter_id' => $booker->id,
            'user_id' => $invitee->id,
            'invitable_type' => 'rehearsal_reservation',
            'invitable_id' => $reservation->id,
            'status' => 'pending',
        ]);

        // Travel to 30 hours before — outside the 23-25 hour window
        $this->travelTo($reservation->reserved_at->copy()->subHours(30));

        $this->artisan('rehearsals:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    });
});
