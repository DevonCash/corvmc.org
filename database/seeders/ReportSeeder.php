<?php

namespace Database\Seeders;

use App\Models\Band;
use App\Models\Event;
use App\Models\MemberProfile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing content to report
        $events = Event::published()->take(10)->get();
        $bands = Band::take(5)->get();
        $profiles = MemberProfile::take(5)->get();
        $users = User::take(10)->get();

        if ($events->isEmpty() && $bands->isEmpty() && $profiles->isEmpty()) {
            $this->command->warn('No events, bands, or member profiles found. Make sure to run other seeders first.');

            return;
        }

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Make sure to run UserSeeder first.');

            return;
        }

        $reportsCreated = 0;

        // Create reports for events
        foreach ($events->take(5) as $event) {
            $reportCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $reportCount; $i++) {
                // Mix of pending, upheld, and dismissed
                $status = fake()->randomElement(['pending', 'upheld', 'dismissed']);

                $report = Report::create([
                    'reportable_type' => Event::class,
                    'reportable_id' => $event->id,
                    'reported_by_id' => $users->random()->id,
                    'reason' => fake()->randomElement(['inappropriate_content', 'spam', 'misleading_info', 'harassment']),
                    'custom_reason' => fake()->optional(0.3)->sentence(),
                    'status' => $status,
                    'resolved_by_id' => $status !== 'pending' ? $users->random()->id : null,
                    'resolved_at' => $status !== 'pending' ? fake()->dateTimeBetween('-1 week', 'now') : null,
                    'resolution_notes' => $status !== 'pending' ? fake()->sentence() : null,
                ]);

                $reportsCreated++;
            }
        }

        // Create reports for bands
        foreach ($bands->take(3) as $band) {
            $report = Report::create([
                'reportable_type' => Band::class,
                'reportable_id' => $band->id,
                'reported_by_id' => $users->random()->id,
                'reason' => fake()->randomElement(['inappropriate_content', 'copyright', 'misleading_info']),
                'custom_reason' => fake()->optional(0.3)->sentence(),
                'status' => 'pending',
            ]);

            $reportsCreated++;
        }

        // Create reports for member profiles
        foreach ($profiles->take(3) as $profile) {
            $status = fake()->randomElement(['pending', 'upheld', 'dismissed']);

            $report = Report::create([
                'reportable_type' => MemberProfile::class,
                'reportable_id' => $profile->id,
                'reported_by_id' => $users->random()->id,
                'reason' => fake()->randomElement(['inappropriate_content', 'fake_profile', 'harassment']),
                'custom_reason' => fake()->optional(0.3)->sentence(),
                'status' => $status,
                'resolved_by_id' => $status !== 'pending' ? $users->random()->id : null,
                'resolved_at' => $status !== 'pending' ? fake()->dateTimeBetween('-2 weeks', 'now') : null,
                'resolution_notes' => $status !== 'pending' ? fake()->sentence() : null,
            ]);

            $reportsCreated++;
        }

        // Create some escalated reports
        if ($events->count() > 5) {
            $report = Report::create([
                'reportable_type' => Event::class,
                'reportable_id' => $events->random()->id,
                'reported_by_id' => $users->random()->id,
                'reason' => 'harassment',
                'custom_reason' => 'This requires immediate attention from the board.',
                'status' => 'escalated',
                'resolved_by_id' => $users->random()->id,
                'resolved_at' => now(),
                'resolution_notes' => 'Escalated to board for review.',
            ]);

            $reportsCreated++;
        }

        $this->command->info("Created {$reportsCreated} reports across events, bands, and member profiles.");
    }
}
