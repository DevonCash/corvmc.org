<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\Band;
use App\Services\ReservationService;
use App\Services\UserSubscriptionService;
use App\Services\BandService;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationCreatedNotification;
use App\Notifications\ReservationConfirmationReminderNotification;
use App\Notifications\ReservationReminderNotification;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\DonationReceivedNotification;
use App\Notifications\BandInvitationNotification;
use App\Notifications\MembershipExpiredNotification;
use App\Notifications\MembershipRenewalReminderNotification;
use App\Notifications\NewMemberWelcomeNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Console\Command;

class TestNotificationSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:notifications {--send : Actually send notifications (otherwise just show what would be sent)}';

    /**
     * The console command description.
     */
    protected $description = 'Test all notification types in the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shouldSend = $this->option('send');

        $this->info('🔔 Testing Notification System');
        $this->line('===============================');

        if (!$shouldSend) {
            $this->warn('Running in DRY RUN mode. Use --send to actually send notifications.');
            $this->line('');
        }

        $testUser = $this->getOrCreateTestUser();

        try {
            // 1. Test Reservation Notifications
            $this->info('🏠 1. Testing Reservation Notifications...');
            $this->testReservationNotifications($testUser, $shouldSend);

            // 2. Test Donation Notifications
            $this->info('💰 2. Testing Donation Notifications...');
            $this->testDonationNotifications($testUser, $shouldSend);

            // 3. Test Band Invitation Notifications
            $this->info('🎵 3. Testing Band Invitation Notifications...');
            $this->testBandNotifications($testUser, $shouldSend);

            // 4. Test Membership Notifications
            $this->info('👥 4. Testing Membership Notifications...');
            $this->testMembershipNotifications($testUser, $shouldSend);

            // 5. Test Authentication Notifications
            $this->info('🔐 5. Testing Authentication Notifications...');
            $this->testAuthNotifications($testUser, $shouldSend);

            // 6. Show notification summary
            $this->info('📊 6. Notification Summary...');
            $this->showNotificationSummary($testUser);

            $this->line('');
            $this->info('✅ All notification tests completed!');

            if ($shouldSend) {
                $this->comment('Check your email and database notifications for the test user.');
            } else {
                $this->comment('Run with --send to actually send the notifications.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Test failed with error: ' . $e->getMessage());
            return 1;
        }
    }

    protected function getOrCreateTestUser(): User
    {
        $user = User::where('email', 'notifications-test@corvmc.org')->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Notification Test User',
                'email' => 'notifications-test@corvmc.org',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('sustaining member');
            $this->line("   ✓ Created test user: {$user->email}");
        } else {
            $this->line("   ✓ Using existing test user: {$user->email}");
        }

        return $user;
    }

    protected function testReservationNotifications(User $user, bool $shouldSend): void
    {
        // Create a test reservation
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'reserved_at' => now()->addDay()->setTime(14, 0), // Tomorrow at 2 PM
            'reserved_until' => now()->addDay()->setTime(16, 0), // Until 4 PM
            'cost' => 15.00,
            'hours_used' => 2.0,
            'free_hours_used' => 0.0,
            'status' => 'confirmed',
            'notes' => 'Test reservation for notification system',
        ]);

        $this->line("   • Created test reservation: {$reservation->time_range}");

        // Test reservation creation notification (for pending status)
        $pendingReservation = $reservation->replicate();
        $pendingReservation->status = 'pending';
        if ($shouldSend) {
            $user->notify(new ReservationCreatedNotification($pendingReservation));
            $this->line("   ✓ Sent reservation creation notification (pending)");
        } else {
            $this->line("   → Would send reservation creation notification (pending)");
        }

        // Test reservation confirmation notification
        if ($shouldSend) {
            $user->notify(new ReservationConfirmedNotification($reservation));
            $this->line("   ✓ Sent reservation confirmation notification");
        } else {
            $this->line("   → Would send reservation confirmation notification");
        }

        // Test reservation confirmation reminder (for pending reservations 3 days before)
        if ($shouldSend) {
            $user->notify(new ReservationConfirmationReminderNotification($pendingReservation));
            $this->line("   ✓ Sent reservation confirmation reminder notification");
        } else {
            $this->line("   → Would send reservation confirmation reminder notification");
        }

        // Test reservation reminder notification
        if ($shouldSend) {
            $user->notify(new ReservationReminderNotification($reservation));
            $this->line("   ✓ Sent reservation reminder notification");
        } else {
            $this->line("   → Would send reservation reminder notification");
        }

        // Test reservation cancelled notification
        if ($shouldSend) {
            $user->notify(new ReservationCancelledNotification($reservation));
            $this->line("   ✓ Sent reservation cancelled notification");
        } else {
            $this->line("   → Would send reservation cancelled notification");
        }

        // Clean up
        $reservation->delete();
    }

    protected function testDonationNotifications(User $user, bool $shouldSend): void
    {
        // Create test transactions
        $oneTimeTransaction = Transaction::create([
            'transaction_id' => 'test-' . uniqid(),
            'email' => $user->email,
            'amount' => 25.00,
            'currency' => 'USD',
            'type' => 'one-time',
            'response' => ['test' => 'data'],
        ]);

        $recurringTransaction = Transaction::create([
            'transaction_id' => 'test-recurring-' . uniqid(),
            'email' => $user->email,
            'amount' => 15.00,
            'currency' => 'USD',
            'type' => 'recurring',
            'response' => ['test' => 'data'],
        ]);

        $this->line("   • Created test transactions: one-time ($25) and recurring ($15)");

        // Test one-time donation notification
        if ($shouldSend) {
            $user->notify(new DonationReceivedNotification($oneTimeTransaction));
            $this->line("   ✓ Sent one-time donation thank you notification");
        } else {
            $this->line("   → Would send one-time donation thank you notification");
        }

        // Test recurring donation notification
        if ($shouldSend) {
            $user->notify(new DonationReceivedNotification($recurringTransaction));
            $this->line("   ✓ Sent recurring donation thank you notification");
        } else {
            $this->line("   → Would send recurring donation thank you notification");
        }

        // Clean up
        $oneTimeTransaction->delete();
        $recurringTransaction->delete();
    }

    protected function testBandNotifications(User $user, bool $shouldSend): void
    {
        // Create a test band
        $band = Band::create([
            'name' => 'Test Notification Band',
            'bio' => 'A band created for testing notification system',
            'owner_id' => $user->id,
            'visibility' => 'public',
        ]);

        $this->line("   • Created test band: {$band->name}");

        // Create another user to invite
        $inviteUser = User::firstOrCreate(
            ['email' => 'band-invite-test@corvmc.org'],
            [
                'name' => 'Band Invite Test User',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        // Test band invitation notification
        if ($shouldSend) {
            $inviteUser->notify(new BandInvitationNotification($band, 'member', 'Guitarist'));
            $this->line("   ✓ Sent band invitation notification to {$inviteUser->email}");
        } else {
            $this->line("   → Would send band invitation notification to {$inviteUser->email}");
        }

        // Clean up
        $band->delete();
    }

    protected function testMembershipNotifications(User $user, bool $shouldSend): void
    {
        $this->line("   • Testing membership expiry and renewal notifications");

        // Test membership renewal reminder (7 days)
        if ($shouldSend) {
            $user->notify(new MembershipRenewalReminderNotification($user, 7));
            $this->line("   ✓ Sent membership renewal reminder (7 days)");
        } else {
            $this->line("   → Would send membership renewal reminder (7 days)");
        }

        // Test membership renewal reminder (1 day)
        if ($shouldSend) {
            $user->notify(new MembershipRenewalReminderNotification($user, 1));
            $this->line("   ✓ Sent membership renewal reminder (1 day)");
        } else {
            $this->line("   → Would send membership renewal reminder (1 day)");
        }

        // Test membership expired notification
        if ($shouldSend) {
            $user->notify(new MembershipExpiredNotification($user));
            $this->line("   ✓ Sent membership expired notification");
        } else {
            $this->line("   → Would send membership expired notification");
        }

        // Test new member welcome notification
        if ($shouldSend) {
            $user->notify(new NewMemberWelcomeNotification($user));
            $this->line("   ✓ Sent new member welcome notification");
        } else {
            $this->line("   → Would send new member welcome notification");
        }
    }

    protected function testAuthNotifications(User $user, bool $shouldSend): void
    {
        $this->line("   • Testing authentication-related notifications");

        // Test password reset notification
        if ($shouldSend) {
            $user->notify(new PasswordResetNotification('test-token'));
            $this->line("   ✓ Sent password reset notification");
        } else {
            $this->line("   → Would send password reset notification");
        }

        // Test email verification notification
        if ($shouldSend) {
            $user->notify(new EmailVerificationNotification());
            $this->line("   ✓ Sent email verification notification");
        } else {
            $this->line("   → Would send email verification notification");
        }
    }

    protected function showNotificationSummary(User $user): void
    {
        $totalNotifications = $user->notifications()->count();
        $unreadNotifications = $user->unreadNotifications()->count();

        $this->line("   • Total notifications for test user: {$totalNotifications}");
        $this->line("   • Unread notifications: {$unreadNotifications}");

        if ($totalNotifications > 0) {
            $this->line("   • Recent notification types:");
            $recent = $user->notifications()->latest()->limit(5)->get();
            foreach ($recent as $notification) {
                $type = class_basename($notification->type);
                $title = $notification->data['title'] ?? $type;
                $this->line("     - {$title} ({$type})");
            }
        }
    }
}
