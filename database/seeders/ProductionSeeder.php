<?php

namespace Database\Seeders;

use App\Models\Band;
use App\Models\Production;
use App\Models\User;
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
        $bands = Band::take(10)->get();

        if ($users->isEmpty() || $bands->isEmpty()) {
            $this->command->warn('No users or bands found. Make sure to run UserSeeder and BandSeeder first.');

            return;
        }

        // Create upcoming productions with unique times
        $upcomingProductions = collect();
        for ($i = 0; $i < 8; $i++) {
            $production = Production::factory()
                ->upcoming()
                ->create([
                    'manager_id' => $users->random()->id,
                ]);
            $upcomingProductions->push($production);
        }

        // Create completed productions with unique times
        $completedProductions = collect();
        for ($i = 0; $i < 12; $i++) {
            $production = Production::factory()
                ->completed()
                ->create([
                    'manager_id' => $users->random()->id,
                ]);
            $completedProductions->push($production);
        }

        // Create some in-progress productions with unique times
        $inProgressProductions = collect();
        for ($i = 0; $i < 3; $i++) {
            $production = Production::factory()
                ->create([
                    'status' => 'in-production',
                    'manager_id' => $users->random()->id,
                    'published_at' => null,
                ]);
            $inProgressProductions->push($production);
        }

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

        $this->command->info('Created '.$allProductions->count().' productions with performers and genres.');
    }
}
