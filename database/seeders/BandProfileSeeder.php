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
        
        // Create touring bands (no owner)
        $this->createTouringBands();
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
            $owner = $users->random();
            $band = BandProfile::factory()
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Led Zeppelin', 'Pink Floyd', 'Arctic Monkeys', 'The Strokes'])
                ->create([
                    'name' => $bandData['name'],
                    'owner_id' => $owner->id,
                ]);

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
            $owner = $users->random();
            $band = BandProfile::factory()
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Miles Davis', 'John Coltrane', 'Duke Ellington', 'Bill Evans'])
                ->create([
                    'name' => $bandData['name'],
                    'owner_id' => $owner->id,
                ]);

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
            $owner = $users->random();
            $band = BandProfile::factory()
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Daft Punk', 'Aphex Twin', 'Burial', 'Boards of Canada'])
                ->create([
                    'name' => $bandData['name'],
                    'owner_id' => $owner->id,
                ]);

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
            $owner = $users->random();
            $band = BandProfile::factory()
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Bob Dylan', 'Joni Mitchell', 'Fleet Foxes', 'The Lumineers'])
                ->create([
                    'name' => $bandData['name'],
                    'owner_id' => $owner->id,
                ]);

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
            $owner = $users->random();
            $band = BandProfile::factory()
                ->public()
                ->withGenres($bandData['genres'])
                ->withInfluences(['Bach', 'Mozart', 'Beethoven', 'Debussy'])
                ->create([
                    'name' => $bandData['name'],
                    'owner_id' => $owner->id,
                ]);

            $this->attachMembers($band, $users, $bandData['memberCount']);
        }
    }

    private function createTouringBands(): void
    {
        $touringBands = [
            [
                'name' => 'The Distant Shores',
                'hometown' => 'Portland, OR',
                'genres' => ['Indie Rock', 'Alternative'],
                'bio' => 'An indie rock quartet from Portland known for their atmospheric soundscapes and introspective lyrics. Currently on their West Coast tour.',
                'contact' => [
                    'email' => 'booking@distantshores.com',
                    'phone' => '(503) 555-0123'
                ]
            ],
            [
                'name' => 'Velvet Thunder',
                'hometown' => 'Nashville, TN',
                'genres' => ['Rock', 'Blues'],
                'bio' => 'A high-energy rock band from Nashville bringing southern blues-infused rock to venues across the country.',
                'contact' => [
                    'email' => 'management@velvetthunder.net',
                    'phone' => '(615) 555-0456'
                ]
            ],
            [
                'name' => 'Luna Sol',
                'hometown' => 'Los Angeles, CA',
                'genres' => ['Folk', 'Latin'],
                'bio' => 'A folk duo blending Latin American influences with contemporary songwriting. Based in LA but touring nationwide.',
                'contact' => [
                    'email' => 'hello@lunasol.music',
                    'phone' => '(323) 555-0789'
                ]
            ],
            [
                'name' => 'Circuit Breakers',
                'hometown' => 'Seattle, WA',
                'genres' => ['Electronic', 'Synthwave'],
                'bio' => 'Electronic music producers turned live performers, bringing synthwave and retro-futurism to the stage.',
                'contact' => [
                    'email' => 'bookings@circuitbreakers.io',
                    'phone' => '(206) 555-0321'
                ]
            ],
            [
                'name' => 'Prairie Wind',
                'hometown' => 'Austin, TX',
                'genres' => ['Country', 'Americana'],
                'bio' => 'Traditional country with a modern twist, this Austin-based band brings authentic storytelling and tight harmonies.',
                'contact' => [
                    'email' => 'contact@prairiewindband.com',
                    'phone' => '(512) 555-0654'
                ]
            ]
        ];

        foreach ($touringBands as $bandData) {
            $band = BandProfile::withTouringBands()->create([
                'name' => $bandData['name'],
                'hometown' => $bandData['hometown'],
                'bio' => $bandData['bio'],
                'contact' => $bandData['contact'],
                'owner_id' => null, // This makes it a touring band
                'visibility' => 'private', // Touring bands should be private by default
            ]);

            // Add genres
            foreach ($bandData['genres'] as $genre) {
                $band->attachTag($genre, 'genre');
            }

            $this->command->info("Created touring band: {$band->name}");
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