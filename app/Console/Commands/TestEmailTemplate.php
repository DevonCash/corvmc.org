<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Reservation;
use App\Notifications\ReservationCreatedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailTemplate extends Command
{
    protected $signature = 'test:email-template';
    protected $description = 'Test the CMC branded email template';

    public function handle()
    {
        $this->info('🎨 Testing CMC Branded Email Template');
        
        // Create a test user
        $user = User::firstOrCreate(
            ['email' => 'template-test@corvmc.org'],
            [
                'name' => 'Template Test User',
                'password' => bcrypt('password123')
            ]
        );

        // Create a test reservation
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'reserved_at' => now()->addDays(8)->setTime(14, 0),
            'reserved_until' => now()->addDays(8)->setTime(16, 0),
            'status' => 'pending',
            'cost' => 25.00,
            'hours_used' => 2,
            'free_hours_used' => 0,
            'notes' => 'Testing the new CMC branded email template!',
        ]);

        $this->line('   • Created test reservation and user');
        
        try {
            // Send the notification
            $user->notify(new ReservationCreatedNotification($reservation));
            $this->info('   ✅ CMC branded email template sent successfully!');
            $this->line('   📧 Check your email logs or inbox to see the branded design');
            $this->line('   🎵 Features: CMC orange branding, Lexend font, gradient header, custom footer');
        } catch (\Exception $e) {
            $this->error('   ❌ Failed to send email: ' . $e->getMessage());
        } finally {
            // Clean up
            $reservation->delete();
            $this->line('   🧹 Test data cleaned up');
        }
        
        $this->info('✨ Email template test completed!');
        return 0;
    }
}