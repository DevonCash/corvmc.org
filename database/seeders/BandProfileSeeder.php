<?php

namespace Database\Seeders;

use App\Models\BandProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class BandProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing users to be band owners
        $users = User::inRandomOrder()->limit(20)->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create various types of bands
        $this->createRockBands($users);
        $this->createJazzEnsembles($users);
        $this->createElectronicActs($users);
        $this->createFolkGroups($users);
        $this->createClassicalEnsembles($users);
    }

    private function createRockBands($users): void
    {
        $rockBands = [
            ['name' => 'Midnight Echoes', 'genres' => ['Rock', 'Alternative'], 'memberCount' => 4],
            ['name' => 'Electric Dreams', 'genres' => ['Rock', 'Electronic'], 'memberCount' => 3],
            ['name' => 'The Voltage', 'genres' => ['Rock', 'Punk'], 'memberCount' => 5],
            ['name' => 'Neon Shadows', 'genres' => ['Rock', 'Indie'], 'memberCount' => 4],
        ];

        foreach ($rockBands as $bandData) {
            $band = BandProfile::factory()
                ->for($users->random(), 'owner')
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Led Zeppelin', 'Pink Floyd', 'Arctic Monkeys', 'The Strokes'])
                ->create(['name' => $bandData['name']]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function createJazzEnsembles($users): void
    {
        $jazzGroups = [
            ['name' => 'Blue Note Collective', 'genres' => ['Jazz', 'Blues'], 'memberCount' => 6],
            ['name' => 'The Swing Society', 'genres' => ['Jazz', 'Swing'], 'memberCount' => 8],
            ['name' => 'Modern Jazz Quartet', 'genres' => ['Jazz', 'Contemporary'], 'memberCount' => 4],
        ];

        foreach ($jazzGroups as $bandData) {
            $band = BandProfile::factory()
                ->for($users->random(), 'owner')
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Miles Davis', 'John Coltrane', 'Duke Ellington', 'Bill Evans'])
                ->create(['name' => $bandData['name']]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function createElectronicActs($users): void
    {
        $electronicActs = [
            ['name' => 'Digital Pulse', 'genres' => ['Electronic', 'Techno'], 'memberCount' => 2],
            ['name' => 'Synth Wave', 'genres' => ['Electronic', 'Synthwave'], 'memberCount' => 1],
            ['name' => 'Bass Frequency', 'genres' => ['Electronic', 'Drum & Bass'], 'memberCount' => 3],
        ];

        foreach ($electronicActs as $bandData) {
            $band = BandProfile::factory()
                ->for($users->random(), 'owner')
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Daft Punk', 'Aphex Twin', 'Burial', 'Boards of Canada'])
                ->create(['name' => $bandData['name']]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function createFolkGroups($users): void
    {
        $folkGroups = [
            ['name' => 'Woodland Harmony', 'genres' => ['Folk', 'Acoustic'], 'memberCount' => 3],
            ['name' => 'River Stone', 'genres' => ['Folk', 'Country'], 'memberCount' => 4],
            ['name' => 'The Wanderers', 'genres' => ['Folk', 'Indie Folk'], 'memberCount' => 5],
        ];

        foreach ($folkGroups as $bandData) {
            $band = BandProfile::factory()
                ->for($users->random(), 'owner')
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Bob Dylan', 'Joni Mitchell', 'Fleet Foxes', 'The Lumineers'])
                ->create(['name' => $bandData['name']]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function createClassicalEnsembles($users): void
    {
        $classicalGroups = [
            ['name' => 'Chamber Orchestra', 'genres' => ['Classical', 'Chamber'], 'memberCount' => 12],
            ['name' => 'String Quartet No. 7', 'genres' => ['Classical', 'String Quartet'], 'memberCount' => 4],
        ];

        foreach ($classicalGroups as $bandData) {
            $band = BandProfile::factory()
                ->for($users->random(), 'owner')
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Bach', 'Mozart', 'Beethoven', 'Debussy'])
                ->create(['name' => $bandData['name']]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function attachMembers(BandProfile $band, $users, int $memberCount): void
    {
        // Always include the owner as a member
        $owner = $band->owner;
        $band->members()->attach($owner->id, [
            'role' => 'admin',
            'position' => fake()->randomElement(['Lead Vocalist', 'Guitarist', 'Keyboardist', 'Drummer', 'Bassist']),
        ]);

        // Add additional members (subtract 1 since owner is already added)
        $additionalMembers = $users->except($owner->id)->random(min($memberCount - 1, $users->count() - 1));
        
        $positions = [
            'Lead Vocalist', 'Backing Vocalist', 'Lead Guitarist', 'Rhythm Guitarist', 'Bassist',
            'Drummer', 'Keyboardist', 'Pianist', 'Saxophonist', 'Trumpeter', 'Violinist',
            'Cellist', 'Flautist', 'Percussionist', 'Sound Engineer', 'Producer'
        ];

        foreach ($additionalMembers as $member) {
            $band->members()->attach($member->id, [
                'role' => 'member',
                'position' => fake()->randomElement($positions),
            ]);
        }
    }
}