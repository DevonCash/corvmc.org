<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create new users for staff who need user accounts
        $jamieUser = User::firstOrCreate(
            ['email' => 'jamie@corvallis-music.org'],
            [
                'name' => 'Jamie Thompson',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        $taylorUser = User::firstOrCreate(
            ['email' => 'taylor@corvallis-music.org'],
            [
                'name' => 'Taylor Brooks',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        $mikeUser = User::firstOrCreate(
            ['email' => 'mike.chen@example.com'],
            [
                'name' => 'Mike Chen',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        // Create users for staff who don't need login accounts
        $sarahUser = User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            [
                'name' => 'Sarah Johnson',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        $alexUser = User::firstOrCreate(
            ['email' => 'alex@example.com'],
            [
                'name' => 'Alex Chen',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        // Create corresponding StaffProfile records using relationship
        $sarahUser->staffProfile()->updateOrCreate(
            ['user_id' => $sarahUser->id],
            [
                'name' => 'Sarah Johnson',
                'title' => 'Board President',
                'bio' => 'Local musician and music educator with 15+ years experience in community organizing. Sarah has been instrumental in establishing CMC as a cornerstone of the Corvallis music scene.',
                'type' => 'board',
                'sort_order' => 1,
                'is_active' => true,
                'email' => 'sarah@example.com',
                'social_links' => [
                    ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/sarahjohnsonmusic'],
                    ['platform' => 'website', 'url' => 'https://sarahjohnsonmusic.com'],
                ],
            ]
        );

        $mikeUser->staffProfile()->updateOrCreate(
            ['user_id' => $mikeUser->id],
            [
                'name' => 'Mike Chen',
                'title' => 'Treasurer',
                'bio' => 'Accountant and bassist who brings financial expertise and passion for supporting local arts. Mike ensures CMC\'s financial stability while pursuing his love of jazz music.',
                'type' => 'board',
                'sort_order' => 2,
                'is_active' => true,
                'email' => 'mike.chen@example.com',
                'social_links' => [
                    ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/mikechen-cpa'],
                ],
            ]
        );

        $alexUser->staffProfile()->updateOrCreate(
            ['user_id' => $alexUser->id],
            [
                'name' => 'Alex Chen',
                'title' => 'Secretary',
                'bio' => 'Event coordinator and drummer who helps organize our community events and outreach. Alex has a background in nonprofit management and a passion for building inclusive spaces.',
                'type' => 'board',
                'sort_order' => 3,
                'is_active' => true,
                'email' => 'alex@example.com',
                'social_links' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/alexdrums'],
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/alexrivera'],
                ],
            ]
        );

        $jamieUser->staffProfile()->updateOrCreate(
            ['user_id' => $jamieUser->id],
            [
                'name' => 'Jamie Thompson',
                'title' => 'Operations Manager',
                'bio' => 'Handles day-to-day operations, booking, and member services. Jamie ensures everything runs smoothly so musicians can focus on what they do best - making music.',
                'type' => 'staff',
                'sort_order' => 1,
                'is_active' => true,
                'email' => 'jamie@corvallis-music.org',
            ]
        );

        $taylorUser->staffProfile()->updateOrCreate(
            ['user_id' => $taylorUser->id],
            [
                'name' => 'Taylor Brooks',
                'title' => 'Program Coordinator',
                'bio' => 'Organizes events, workshops, and community outreach programs. Taylor has a background in arts administration and loves connecting musicians with opportunities to grow and collaborate.',
                'type' => 'staff',
                'sort_order' => 2,
                'is_active' => true,
                'email' => 'taylor@corvallis-music.org',
                'social_links' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/taylor.brooks.music'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/taylorbrooksmusic'],
                ],
            ]
        );

        // Generate additional random StaffProfile records using factory
        StaffProfile::factory()->board()->count(2)->create();
        StaffProfile::factory()->staff()->count(3)->create();

        // Create a board member without a specific title
        $patriciaUser = User::firstOrCreate(
            ['email' => 'patricia@example.com'],
            [
                'name' => 'Dr. Patricia Williams',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        $patriciaUser->staffProfile()->updateOrCreate(
            ['user_id' => $patriciaUser->id],
            [
                'name' => 'Dr. Patricia Williams',
                'title' => null, // No specific title
                'bio' => 'Retired music professor and community advocate. Patricia brings decades of experience in music education and a deep commitment to fostering artistic growth in our community.',
                'type' => 'board',
                'sort_order' => 10,
                'is_active' => true,
                'email' => 'patricia@example.com',
                'social_links' => null,
            ]
        );

        // Create one inactive profile to test filtering
        StaffProfile::factory()->inactive()->create([
            'name' => 'Former Staff Member',
            'title' => 'Former Program Assistant',
            'bio' => 'This person is no longer with the organization.',
        ]);

        $this->command->info('Created StaffProfile records for board and staff members');
    }
}
