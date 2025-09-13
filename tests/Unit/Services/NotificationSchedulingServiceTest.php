<?php

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationReminderNotification;
use App\Notifications\ReservationConfirmationNotification;
use App\Notifications\MembershipReminderNotification;
use App\Facades\NotificationSchedulingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

describe('sendReservationReminders', function () {
    it('sends reminders for confirmed reservations tomorrow', function () {
        $user = $this->createUser();
        $tomorrow = Carbon::now()->addDay();

        // Create confirmed reservation for tomorrow
        $reservation = $this->createReservation([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'reserved_at' => $tomorrow->copy()->setHour(14),
            'reserved_until' => $tomorrow->copy()->setHour(16),
        ]);

        // Create reservation for different day (should be ignored)
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addDays(2)->setHour(14),
            'reserved_until' => Carbon::now()->addDays(2)->setHour(16),
        ]);

        $results = NotificationSchedulingService::sendReservationReminders();

        expect($results['total'])->toBe(1)
            ->and($results['sent'])->toBe(1)
            ->and($results['failed'])->toBe(0)
            ->and($results['reservations'])->toHaveCount(1)
            ->and($results['reservations'][0]['status'])->toBe('sent')
            ->and($results['reservations'][0]['user_name'])->toBe($user->name);

        Notification::assertSentTo($user, ReservationReminderNotification::class);
    });

    it('ignores pending and cancelled reservations', function () {
        $user = $this->createUser();
        $tomorrow = Carbon::now()->addDay();

        // Create pending reservation
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'pending',
            'reserved_at' => $tomorrow->copy()->setHour(14),
            'reserved_until' => $tomorrow->copy()->setHour(16),
        ]);

        // Create cancelled reservation
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'cancelled',
            'reserved_at' => $tomorrow->copy()->setHour(18),
            'reserved_until' => $tomorrow->copy()->setHour(20),
        ]);

        $results = NotificationSchedulingService::sendReservationReminders();

        expect($results['total'])->toBe(0)
            ->and($results['sent'])->toBe(0);

        Notification::assertNothingSent();
    });

    it('handles dry run mode', function () {
        $user = $this->createUser();
        $tomorrow = Carbon::now()->addDay();

        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'reserved_at' => $tomorrow->copy()->setHour(14),
            'reserved_until' => $tomorrow->copy()->setHour(16),
        ]);

        $results = NotificationSchedulingService::sendReservationReminders(dryRun: true);

        expect($results['total'])->toBe(1)
            ->and($results['sent'])->toBe(0)
            ->and($results['reservations'][0]['status'])->toBe('dry_run');

        Notification::assertNothingSent();
    });

    it('tracks failed notifications', function () {
        $user = $this->createUser();
        $tomorrow = Carbon::now()->addDay();

        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'reserved_at' => $tomorrow->copy()->setHour(14),
            'reserved_until' => $tomorrow->copy()->setHour(16),
        ]);

        // Mock notification failure
        Notification::shouldReceive('send')
            ->andThrow(new Exception('Email service unavailable'));

        $results = NotificationSchedulingService::sendReservationReminders();

        expect($results['total'])->toBe(1)
            ->and($results['sent'])->toBe(0)
            ->and($results['failed'])->toBe(1)
            ->and($results['errors'])->toHaveCount(1)
            ->and($results['reservations'][0]['status'])->toBe('failed');
    });
});

describe('sendReservationConfirmationReminders', function () {
    it('sends confirmation reminders for old pending reservations', function () {
        $user = $this->createUser();

        // Create pending reservation from 2 days ago
        $reservation = $this->createReservation([
            'user_id' => $user->id,
            'status' => 'pending',
            'reserved_at' => Carbon::now()->addWeek(),
            'reserved_until' => Carbon::now()->addWeek()->addHours(2),
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $results = NotificationSchedulingService::sendReservationConfirmationReminders();

        expect($results['total'])->toBe(1)
            ->and($results['sent'])->toBe(1)
            ->and($results['reservations'][0]['status'])->toBe('sent');

        Notification::assertSentTo($user, ReservationConfirmationNotification::class);
    });

    it('ignores recently created pending reservations', function () {
        $user = $this->createUser();

        // Create pending reservation from 12 hours ago (too recent)
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'pending',
            'reserved_at' => Carbon::now()->addWeek(),
            'reserved_until' => Carbon::now()->addWeek()->addHours(2),
            'created_at' => Carbon::now()->subHours(12),
        ]);

        $results = NotificationSchedulingService::sendReservationConfirmationReminders();

        expect($results['total'])->toBe(0);

        Notification::assertNothingSent();
    });

    it('ignores past reservations even if pending', function () {
        $user = $this->createUser();

        // Create pending reservation in the past
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'pending',
            'reserved_at' => Carbon::now()->subDay(),
            'reserved_until' => Carbon::now()->subDay()->addHours(2),
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $results = NotificationSchedulingService::sendReservationConfirmationReminders();

        expect($results['total'])->toBe(0);

        Notification::assertNothingSent();
    });

    it('ignores confirmed reservations', function () {
        $user = $this->createUser();

        // Create confirmed reservation (should be ignored)
        $this->createReservation([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addWeek(),
            'reserved_until' => Carbon::now()->addWeek()->addHours(2),
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $results = NotificationSchedulingService::sendReservationConfirmationReminders();

        expect($results['total'])->toBe(0);

        Notification::assertNothingSent();
    });
});

describe('sendMembershipReminders', function () {
    it('sends reminders to inactive non-sustaining users', function () {
        // Use dry run to avoid sending to other test users
        $user = $this->createUser([
            'email_verified_at' => Carbon::now(),
        ]);

        $results = NotificationSchedulingService::sendMembershipReminders(dryRun: true, inactiveDays: 90);

        // Check that our user is in the results
        $userFound = false;
        foreach ($results['users'] as $resultUser) {
            if ($resultUser['email'] === $user->email) {
                $userFound = true;
                expect($resultUser['status'])->toBe('dry_run');
                break;
            }
        }

        expect($userFound)->toBeTrue('User should be found in inactive users list');
    });

    it('ignores users with recent reservations', function () {
        $user = $this->createUser([
            'email_verified_at' => Carbon::now(),
        ]);

        // Create recent reservation
        $this->createReservation([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $results = NotificationSchedulingService::sendMembershipReminders(dryRun: true, inactiveDays: 90);

        // Check that our user is NOT in the results
        $userFound = false;
        foreach ($results['users'] as $resultUser) {
            if ($resultUser['email'] === $user->email) {
                $userFound = true;
                break;
            }
        }

        expect($userFound)->toBeFalse('User with recent reservations should not be in inactive list');
    });

    it('ignores sustaining members', function () {
        $sustainingMember = $this->createSustainingMember();

        $results = NotificationSchedulingService::sendMembershipReminders(dryRun: false, inactiveDays: 90);

        expect($results['total'])->toBe(0);

        Notification::assertNothingSent();
    });

    it('ignores users without verified email', function () {
        $user = $this->createUser([
            'email_verified_at' => null,
        ]);

        $results = NotificationSchedulingService::sendMembershipReminders(dryRun: false, inactiveDays: 90);

        expect($results['total'])->toBe(0);

        Notification::assertNothingSent();
    });

    it('respects custom inactive days parameter', function () {
        $user1 = $this->createUser([
            'email' => 'user1@test.com',
            'email_verified_at' => Carbon::now(),
        ]);
        $user2 = $this->createUser([
            'email' => 'user2@test.com',
            'email_verified_at' => Carbon::now(),
        ]);

        // User1 has reservation 45 days ago
        $this->createReservation([
            'user_id' => $user1->id,
            'created_at' => Carbon::now()->subDays(45),
        ]);

        // User2 has reservation 75 days ago
        $this->createReservation([
            'user_id' => $user2->id,
            'created_at' => Carbon::now()->subDays(75),
        ]);

        // With 60-day threshold, only user2 should get reminder (dry run)
        $results = NotificationSchedulingService::sendMembershipReminders(dryRun: true, inactiveDays: 60);

        $user1Found = false;
        $user2Found = false;

        foreach ($results['users'] as $resultUser) {
            if ($resultUser['email'] === $user1->email) {
                $user1Found = true;
            } elseif ($resultUser['email'] === $user2->email) {
                $user2Found = true;
            }
        }

        expect($user1Found)->toBeFalse('User1 should not be inactive with 45-day old reservation')
            ->and($user2Found)->toBeTrue('User2 should be inactive with 75-day old reservation');
    });
});

describe('getNotificationStats', function () {
    it('returns correct statistics', function () {
        // Create reservation for tomorrow
        $this->createReservation([
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addDay()->setHour(14),
            'reserved_until' => Carbon::now()->addDay()->setHour(16),
        ]);

        // Create old pending reservation
        $this->createReservation([
            'status' => 'pending',
            'reserved_at' => Carbon::now()->addWeek(),
            'reserved_until' => Carbon::now()->addWeek()->addHours(2),
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // Create inactive user (no recent reservations)
        $inactiveUser = $this->createUser();
        // Don't create any reservations for this user to make them truly inactive

        $stats = NotificationSchedulingService::getNotificationStats();

        expect($stats['reservations_tomorrow'])->toBe(1)
            ->and($stats['pending_reservations'])->toBe(1)
            ->and($stats['inactive_users'])->toBe(1);
    });
});

describe('scheduleCustomNotification', function () {
    it('sends notification immediately when no future date specified', function () {
        $user = $this->createUser();
        $notification = new ReservationReminderNotification(
            $this->createReservation(['user_id' => $user->id])
        );

        $result = NotificationSchedulingService::scheduleCustomNotification($user, $notification);

        expect($result)->toBeTrue();

        Notification::assertSentTo($user, ReservationReminderNotification::class);
    });

    it('logs future notification scheduling', function () {
        $user = $this->createUser();
        $notification = new ReservationReminderNotification(
            $this->createReservation(['user_id' => $user->id])
        );
        $futureDate = Carbon::now()->addHours(2);

        $result = NotificationSchedulingService::scheduleCustomNotification($user, $notification, $futureDate);

        expect($result)->toBeTrue();

        // Should not send immediately for future dates
        Notification::assertNothingSent();
    });

    it('handles notification failures gracefully', function () {
        $user = $this->createUser();
        $notification = new ReservationReminderNotification(
            $this->createReservation(['user_id' => $user->id])
        );

        // Mock notification failure
        Notification::shouldReceive('send')
            ->andThrow(new Exception('Notification failed'));

        $result = NotificationSchedulingService::scheduleCustomNotification($user, $notification);

        expect($result)->toBeFalse();
    });
});

describe('integration scenarios', function () {
    it('handles multiple users with mixed reservation statuses', function () {
        $tomorrow = Carbon::now()->addDay();

        // User with confirmed reservation tomorrow
        $user1 = $this->createUser();
        $this->createReservation([
            'user_id' => $user1->id,
            'status' => 'confirmed',
            'reserved_at' => $tomorrow->copy()->setHour(14),
            'reserved_until' => $tomorrow->copy()->setHour(16),
        ]);

        // User with pending reservation tomorrow (should be ignored for reminders)
        $user2 = $this->createUser();
        $this->createReservation([
            'user_id' => $user2->id,
            'status' => 'pending',
            'reserved_at' => $tomorrow->copy()->setHour(18),
            'reserved_until' => $tomorrow->copy()->setHour(20),
        ]);

        // User with confirmed reservation next week (should be ignored for tomorrow reminders)
        $user3 = $this->createUser();
        $this->createReservation([
            'user_id' => $user3->id,
            'status' => 'confirmed',
            'reserved_at' => Carbon::now()->addWeek(),
            'reserved_until' => Carbon::now()->addWeek()->addHours(2),
        ]);

        $results = NotificationSchedulingService::sendReservationReminders();

        expect($results['total'])->toBe(1)
            ->and($results['sent'])->toBe(1)
            ->and($results['reservations'][0]['user_name'])->toBe($user1->name);

        Notification::assertSentTo($user1, ReservationReminderNotification::class);
        Notification::assertNotSentTo($user2, ReservationReminderNotification::class);
        Notification::assertNotSentTo($user3, ReservationReminderNotification::class);
    });
});
