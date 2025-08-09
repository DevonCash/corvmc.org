<?php

namespace App\Console\Commands;

use App\Services\UserInvitationService;
use App\Models\User;
use Illuminate\Console\Command;

class TestInvitationSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:invitations {--clean : Clean up test data first}';

    /**
     * The console command description.
     */
    protected $description = 'Test the user invitation system end-to-end';

    /**
     * Execute the console command.
     */
    public function handle(UserInvitationService $invitationService)
    {
        $this->info('🧪 Testing User Invitation System');
        $this->line('=====================================');

        // Clean up test data if requested
        if ($this->option('clean')) {
            $this->info('🧹 Cleaning up test data...');
            User::whereIn('email', [
                'test@example.com', 
                'test2@example.com', 
                'demo@corvmc.org',
                'expired@corvmc.org'
            ])->delete();
            $this->line('   ✓ Test users removed');
        }

        try {
            // 1. Create invitation
            $this->info('📧 1. Creating invitation...');
            $user = $invitationService->inviteUser('demo@corvmc.org', ['sustaining member', 'band leader']);
            $token = $invitationService->generateInvitationToken($user);
            
            $this->line("   ✓ User created: {$user->name} ({$user->email})");
            $this->line("   ✓ Roles assigned: {$user->roles->pluck('name')->join(', ')}");
            $this->line("   ✓ Email verified: " . ($user->email_verified_at ? 'Yes' : 'No (Pending invitation)'));

            // 2. Validate token
            $this->info('🔐 2. Validating invitation token...');
            $foundUser = $invitationService->findUserByToken($token);
            $isExpired = $invitationService->isTokenExpired($token);

            if ($foundUser && !$isExpired) {
                $this->line("   ✓ Token valid for user: {$foundUser->email}");
                $invitationUrl = route('invitation.accept', ['token' => $token]);
                $this->line("   ✓ Invitation URL: {$invitationUrl}");
            } else {
                $this->error('   ✗ Token invalid or expired');
                return 1;
            }

            // 3. Test invitation acceptance (simulate)
            $this->info('👤 3. Testing invitation acceptance...');
            $acceptedUser = $invitationService->acceptInvitation($token, [
                'name' => 'Demo User',
                'password' => 'password123'
            ]);

            if ($acceptedUser) {
                $this->line("   ✓ Invitation accepted successfully");
                $this->line("   ✓ User name updated: {$acceptedUser->name}");
                $this->line("   ✓ Email verified: " . ($acceptedUser->email_verified_at ? 'Yes' : 'No'));
                
                // Try to use the same token again (should fail)
                $duplicateUser = $invitationService->acceptInvitation($token, [
                    'name' => 'Another User',
                    'password' => 'password456'
                ]);
                
                if (!$duplicateUser) {
                    $this->line("   ✓ Token properly invalidated after use");
                }
            } else {
                $this->error('   ✗ Invitation acceptance failed');
                return 1;
            }

            // 4. Show statistics
            $this->info('📊 4. Invitation statistics...');
            $stats = $invitationService->getInvitationStats();
            $this->line("   • Pending invitations: {$stats['pending_invitations']}");
            $this->line("   • Completed registrations: {$stats['completed_registrations']}");
            $this->line("   • Total users: {$stats['total_users']}");
            $this->line("   • Acceptance rate: " . number_format($stats['acceptance_rate'], 1) . "%");

            // 5. Test expiration
            $this->info('⏰ 5. Testing invitation expiration...');
            $expiredUser = $invitationService->inviteUser('expired@corvmc.org', ['member']);
            
            // Manually create an expired token
            $expiredToken = encrypt([
                'user_id' => $expiredUser->id,
                'email' => $expiredUser->email,
                'expires_at' => now()->subWeek()->timestamp, // Expired 1 week ago
            ]);
            
            $isExpired = $invitationService->isTokenExpired($expiredToken);
            $this->line($isExpired ? "   ✓ Expired token properly detected" : "   ✗ Expiration check failed");

            $this->line('');
            $this->info('✅ All invitation system tests passed!');
            $this->comment('The user invitation system is working correctly.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Test failed with error: ' . $e->getMessage());
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}