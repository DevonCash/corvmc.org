<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Transaction;
use App\Notifications\MembershipRenewalReminderNotification;
use App\Notifications\MembershipExpiredNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMembershipReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memberships:send-reminders {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send membership renewal reminders and expiry notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('🔄 Checking membership statuses...');
        
        // Get users with membership status based on recurring donations
        $usersWithMemberships = $this->getUsersWithRecentMemberships();
        
        $remindersSent = 0;
        $expiredNotificationsSent = 0;
        
        foreach ($usersWithMemberships as $userData) {
            $user = $userData['user'];
            $lastTransaction = $userData['last_transaction'];
            $daysSinceLastDonation = $userData['days_since_last'];
            
            // Send renewal reminders at 23 days (7 days warning) and 29 days (1 day warning)
            // assuming 30-day renewal cycle
            if ($daysSinceLastDonation == 23) {
                if (!$isDryRun) {
                    $user->notify(new MembershipRenewalReminderNotification($user, 7));
                    $this->info("  ✓ Sent 7-day renewal reminder to {$user->email}");
                } else {
                    $this->line("  → Would send 7-day renewal reminder to {$user->email}");
                }
                $remindersSent++;
            } elseif ($daysSinceLastDonation == 29) {
                if (!$isDryRun) {
                    $user->notify(new MembershipRenewalReminderNotification($user, 1));
                    $this->info("  ✓ Sent 1-day renewal reminder to {$user->email}");
                } else {
                    $this->line("  → Would send 1-day renewal reminder to {$user->email}");
                }
                $remindersSent++;
            } elseif ($daysSinceLastDonation >= 30) {
                // Membership has expired
                if (!$isDryRun) {
                    $user->notify(new MembershipExpiredNotification($user));
                    $this->info("  ✓ Sent membership expired notification to {$user->email}");
                } else {
                    $this->line("  → Would send membership expired notification to {$user->email}");
                }
                $expiredNotificationsSent++;
            }
        }

        $this->line('');
        $this->info("📊 Summary:");
        $this->line("   • Renewal reminders: {$remindersSent}");
        $this->line("   • Expiry notifications: {$expiredNotificationsSent}");

        if ($isDryRun) {
            $this->warn('This was a dry run. No notifications were actually sent.');
            $this->info('Run without --dry-run to actually send the notifications.');
        } else {
            $this->info('Membership notifications have been sent!');
        }

        return 0;
    }

    /**
     * Get users with recent recurring memberships and calculate days since last donation.
     */
    private function getUsersWithRecentMemberships(): array
    {
        $users = [];
        
        // Get users who have made recurring donations (membership payments) in the last 60 days
        $recentMembers = User::whereHas('transactions', function($query) {
            $query->where('type', 'recurring')
                  ->where('amount', '>', 10) // Sustaining member threshold
                  ->where('created_at', '>=', now()->subDays(60));
        })->with(['transactions' => function($query) {
            $query->where('type', 'recurring')
                  ->where('amount', '>', 10)
                  ->orderBy('created_at', 'desc');
        }])->get();

        foreach ($recentMembers as $user) {
            $lastTransaction = $user->transactions->first();
            if ($lastTransaction) {
                $daysSince = now()->diffInDays($lastTransaction->created_at);
                
                $users[] = [
                    'user' => $user,
                    'last_transaction' => $lastTransaction,
                    'days_since_last' => $daysSince,
                ];
            }
        }

        return $users;
    }
}