<?php

namespace CorvMC\Moderation\Database\Seeders;

use App\Models\Band;
use App\Models\Event;
use App\Models\MemberProfile;
use CorvMC\Moderation\Models\Report;
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
        $usedCombinations = [];

        // Create reports for events
        foreach ($events->take(5) as $event) {
            $reportCount = fake()->numberBetween(1, min(3, $users->count()));
            $availableUsers = $users->shuffle();
            $usedForThisEvent = 0;

            foreach ($availableUsers as $user) {
                if ($usedForThisEvent >= $reportCount) {
                    break;
                }

                $key = Event::class.'|'.$event->id.'|'.$user->id;
                if (isset($usedCombinations[$key])) {
                    continue;
                }
                $usedCombinations[$key] = true;

                $status = fake()->randomElement(['pending', 'upheld', 'dismissed']);

                Report::create([
                    'reportable_type' => Event::class,
                    'reportable_id' => $event->id,
                    'reported_by_id' => $user->id,
                    'reason' => fake()->randomElement(['inappropriate_content', 'spam', 'misleading_info', 'harassment']),
                    'custom_reason' => fake()->optional(0.3)->sentence(),
                    'status' => $status,
                    'resolved_by_id' => $status !== 'pending' ? $users->random()->id : null,
                    'resolved_at' => $status !== 'pending' ? fake()->dateTimeBetween('-1 week', 'now') : null,
                    'resolution_notes' => $status !== 'pending' ? fake()->sentence() : null,
                ]);

                $reportsCreated++;
                $usedForThisEvent++;
            }
        }

        // Create reports for bands
        foreach ($bands->take(3) as $band) {
            $user = $users->random();
            $key = Band::class.'|'.$band->id.'|'.$user->id;
            if (isset($usedCombinations[$key])) {
                continue;
            }
            $usedCombinations[$key] = true;

            Report::create([
                'reportable_type' => Band::class,
                'reportable_id' => $band->id,
                'reported_by_id' => $user->id,
                'reason' => fake()->randomElement(['inappropriate_content', 'copyright', 'misleading_info']),
                'custom_reason' => fake()->optional(0.3)->sentence(),
                'status' => 'pending',
            ]);

            $reportsCreated++;
        }

        // Create reports for member profiles
        foreach ($profiles->take(3) as $profile) {
            $user = $users->random();
            $key = MemberProfile::class.'|'.$profile->id.'|'.$user->id;
            if (isset($usedCombinations[$key])) {
                continue;
            }
            $usedCombinations[$key] = true;

            $status = fake()->randomElement(['pending', 'upheld', 'dismissed']);

            Report::create([
                'reportable_type' => MemberProfile::class,
                'reportable_id' => $profile->id,
                'reported_by_id' => $user->id,
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
            $event = $events->random();
            $user = $users->random();
            $key = Event::class.'|'.$event->id.'|'.$user->id;

            if (! isset($usedCombinations[$key])) {
                $usedCombinations[$key] = true;

                Report::create([
                    'reportable_type' => Event::class,
                    'reportable_id' => $event->id,
                    'reported_by_id' => $user->id,
                    'reason' => 'harassment',
                    'custom_reason' => 'This requires immediate attention from the board.',
                    'status' => 'escalated',
                    'resolved_by_id' => $users->random()->id,
                    'resolved_at' => now(),
                    'resolution_notes' => 'Escalated to board for review.',
                ]);

                $reportsCreated++;
            }
        }

        $this->command->info("Created {$reportsCreated} reports across events, bands, and member profiles.");
    }
}
