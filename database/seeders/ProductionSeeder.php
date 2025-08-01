<?php

namespace Database\Seeders;

use App\Models\BandProfile;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing users and bands to work with
        $users = User::take(5)->get();
        $bands = BandProfile::take(10)->get();

        if ($users->isEmpty() || $bands->isEmpty()) {
            $this->command->warn('No users or bands found. Make sure to run UserSeeder and BandSeeder first.');
            return;
        }

        // Create upcoming productions
        $upcomingProductions = Production::factory()
            ->count(8)
            ->upcoming()
            ->create([
                'manager_id' => $users->random()->id,
            ]);

        // Create completed productions
        $completedProductions = Production::factory()
            ->count(12)
            ->completed()
            ->create([
                'manager_id' => $users->random()->id,
            ]);

        // Create some in-progress productions
        $inProgressProductions = Production::factory()
            ->count(3)
            ->create([
                'status' => 'in-production',
                'manager_id' => $users->random()->id,
                'published_at' => null,
            ]);

        // Combine all productions
        $allProductions = $upcomingProductions
            ->concat($completedProductions)
            ->concat($inProgressProductions);

        // Attach bands to productions with realistic performer counts and set lengths
        foreach ($allProductions as $production) {
            $performerCount = fake()->numberBetween(1, 5);
            $selectedBands = $bands->random($performerCount);
            
            foreach ($selectedBands as $index => $band) {
                $production->performers()->attach($band->id, [
                    'order' => $index + 1,
                    'set_length' => fake()->numberBetween(20, 60), // 20-60 minute sets
                ]);
            }

            // Add some tags
            $genres = collect(['rock', 'pop', 'jazz', 'folk', 'electronic', 'indie', 'acoustic', 'blues', 'country', 'hip-hop'])
                ->random(fake()->numberBetween(1, 3));
            
            foreach ($genres as $genre) {
                $production->attachTag($genre, 'genre');
            }
        }

        $this->command->info('Created ' . $allProductions->count() . ' productions with performers and genres.');
    }
}
