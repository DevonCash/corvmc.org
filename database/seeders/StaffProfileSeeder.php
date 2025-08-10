<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StaffProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update existing users with staff profile data
        $sarahUser = User::where('email', 'sarah@example.com')->first();
        if ($sarahUser) {
            $sarahUser->update([
                'staff_title' => 'Board President',
                'staff_bio' => 'Local musician and music educator with 15+ years experience in community organizing. Sarah has been instrumental in establishing CMC as a cornerstone of the Corvallis music scene.',
                'staff_type' => 'board',
                'staff_sort_order' => 1,
                'show_on_about_page' => true,
                'staff_social_links' => [
                    ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/sarahjohnsonmusic'],
                    ['platform' => 'website', 'url' => 'https://sarahjohnsonmusic.com'],
                ],
            ]);
        }

        // Update Alex (existing user from factory)
        $alexUser = User::where('email', 'alex@example.com')->first();
        if ($alexUser) {
            $alexUser->update([
                'staff_title' => 'Secretary',
                'staff_bio' => 'Event coordinator and drummer who helps organize our community events and outreach. Alex has a background in nonprofit management and a passion for building inclusive spaces.',
                'staff_type' => 'board',
                'staff_sort_order' => 3,
                'show_on_about_page' => true,
                'staff_social_links' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/alexdrums'],
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/alexrivera'],
                ],
            ]);
        }

        // Create new users for those not already existing
        $mikeUser = User::create([
            'name' => 'Mike Chen',
            'email' => 'mike.chen@example.com', // Use different email to avoid conflict
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'staff_title' => 'Treasurer',
            'staff_bio' => 'Accountant and bassist who brings financial expertise and passion for supporting local arts. Mike ensures CMC\'s financial stability while pursuing his love of jazz music.',
            'staff_type' => 'board',
            'staff_sort_order' => 2,
            'show_on_about_page' => true,
            'staff_social_links' => [
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/in/mikechen-cpa'],
            ],
        ]);

        $jamieUser = User::create([
            'name' => 'Jamie Thompson',
            'email' => 'jamie@corvallis-music.org',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'staff_title' => 'Operations Manager',
            'staff_bio' => 'Handles day-to-day operations, booking, and member services. Jamie ensures everything runs smoothly so musicians can focus on what they do best - making music.',
            'staff_type' => 'staff',
            'staff_sort_order' => 1,
            'show_on_about_page' => true,
            'staff_social_links' => null,
        ]);

        $taylorUser = User::create([
            'name' => 'Taylor Brooks',
            'email' => 'taylor@corvallis-music.org',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'staff_title' => 'Program Coordinator',
            'staff_bio' => 'Organizes events, workshops, and community outreach programs. Taylor has a background in arts administration and loves connecting musicians with opportunities to grow and collaborate.',
            'staff_type' => 'staff',
            'staff_sort_order' => 2,
            'show_on_about_page' => true,
            'staff_social_links' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/taylor.brooks.music'],
                ['platform' => 'instagram', 'url' => 'https://instagram.com/taylorbrooksmusic'],
            ],
        ]);

        // Create corresponding StaffProfile records
        StaffProfile::create([
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
        ]);

        StaffProfile::create([
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
        ]);

        StaffProfile::create([
            'name' => 'Alex Chen', // Use the same name as the existing user
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
        ]);

        StaffProfile::create([
            'name' => 'Jamie Thompson',
            'title' => 'Operations Manager',
            'bio' => 'Handles day-to-day operations, booking, and member services. Jamie ensures everything runs smoothly so musicians can focus on what they do best - making music.',
            'type' => 'staff',
            'sort_order' => 1,
            'is_active' => true,
            'email' => 'jamie@corvallis-music.org',
        ]);

        StaffProfile::create([
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
        ]);

        // Update some existing random users to have staff profile data
        $randomUsers = User::whereNotIn('email', [
            'admin@corvallismusic.org',
            'sarah@example.com', 
            'alex@example.com',
            'jordan@example.com',
            'morgan@example.com',
            'river@example.com',
            'mike.chen@example.com',
            'jamie@corvallis-music.org',
            'taylor@corvallis-music.org'
        ])->limit(5)->get();

        foreach ($randomUsers->take(3) as $index => $user) {
            $user->update([
                'staff_type' => 'board',
                'show_on_about_page' => true,
                'staff_title' => fake()->randomElement(['Board Member', 'Board Advisor']),
                'staff_bio' => fake()->paragraph(),
                'staff_sort_order' => 10 + $index,
            ]);
        }

        foreach ($randomUsers->skip(3)->take(2) as $index => $user) {
            $user->update([
                'staff_type' => 'staff', 
                'show_on_about_page' => true,
                'staff_title' => fake()->randomElement(['Assistant Coordinator', 'Volunteer Manager', 'Marketing Assistant']),
                'staff_bio' => fake()->paragraph(),
                'staff_sort_order' => 20 + $index,
            ]);
        }

        // Generate additional random StaffProfile records using factory
        StaffProfile::factory()->board()->count(2)->create();
        StaffProfile::factory()->staff()->count(3)->create();
        
        // Create a board member without a specific title
        StaffProfile::create([
            'name' => 'Dr. Patricia Williams',
            'title' => null, // No specific title
            'bio' => 'Retired music professor and community advocate. Patricia brings decades of experience in music education and a deep commitment to fostering artistic growth in our community.',
            'type' => 'board',
            'sort_order' => 10,
            'is_active' => true,
            'email' => 'patricia@example.com',
            'social_links' => null,
        ]);
        
        // Create one inactive profile to test filtering
        StaffProfile::factory()->inactive()->create([
            'name' => 'Former Staff Member',
            'title' => 'Former Program Assistant',
            'bio' => 'This person is no longer with the organization.',
        ]);

        $this->command->info('Updated existing users with staff profile data and created additional StaffProfile records');
    }
}
