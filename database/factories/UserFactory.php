<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'pronouns' => fake()->randomElement([
                null,
                'he/him',
                'she/her',
                'they/them',
                'he/they',
                'she/they',
                'ze/zir',
            ]),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user with the admin role.
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('admin');
        });
    }

    /**
     * Create a sustaining member user.
     */
    public function sustainingMember(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('sustaining member');
        });
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            if (! Role::where('name', $role)->exists()) {
                throw new \InvalidArgumentException("Role '{$role}' does not exist.");
            }
            $user->assignRole($role);
        });
    }

    /**
     * Create a user with profile for seeding (without triggering model events).
     * This avoids conflicts with the User boot method that auto-creates profiles.
     */
    public function withProfile(): static
    {
        return $this->afterCreating(function ($user) {
            // Create profile manually since we disabled events during creation
            \App\Models\MemberProfile::create(['user_id' => $user->id]);
        });
    }

    /**
     * Create a user without a profile by disabling model events.
     * Useful for testing scenarios where you need to manually control profile creation.
     */
    public function withoutProfile(): static
    {
        return $this->configure()
            ->afterMaking(function ($user) {
                // No action needed - just prevent automatic profile creation
            })
            ->afterCreating(function ($user) {
                // Ensure no profile gets created by clearing any that might exist
                $user->profile()?->delete();
            });
    }

    /**
     * Static method to create a user without a profile.
     * Useful for testing scenarios where you need to manually control profile creation.
     */
    public static function createWithoutProfile(array $attributes = [])
    {
        return \App\Models\User::withoutEvents(function () use ($attributes) {
            $factory = static::new();
            if (! empty($attributes)) {
                $factory = $factory->state($attributes);
            }
            $user = $factory->create();

            // Ensure no profile exists by deleting any that might have been created
            \App\Models\MemberProfile::where('user_id', $user->id)->delete();

            return $user;
        });
    }

    /**
     * Static method to create users without events and with profiles for seeding.
     * Returns an array with ['user' => User, 'profile' => MemberProfile]
     */
    public static function createWithProfile(array $userAttributes = [], array $profileAttributes = [])
    {
        return \App\Models\User::withoutEvents(function () use ($userAttributes, $profileAttributes) {
            $factory = static::new();
            if (! empty($userAttributes)) {
                $factory = $factory->state($userAttributes);
            }
            $user = $factory->create();

            // Manually create profile since events are disabled
            $profile = \App\Models\MemberProfile::create(['user_id' => $user->id]);

            // Update profile with provided attributes
            if (! empty($profileAttributes)) {
                $profile->update($profileAttributes);
            }

            return ['user' => $user, 'profile' => $profile];
        });
    }

    /**
     * Static method to create multiple users without events and with profiles for seeding.
     */
    public static function createManyWithProfiles(int $count, array $attributes = [])
    {
        return \App\Models\User::withoutEvents(function () use ($count, $attributes) {
            $factory = static::new();
            if (! empty($attributes)) {
                $factory = $factory->state($attributes);
            }

            return $factory->withProfile()->count($count)->create();
        });
    }

    /**
     * Create admin user with full profile, tags, and flags
     */
    public static function createAdmin()
    {
        $result = static::createWithProfile(
            [
                'name' => 'Admin User',
                'pronouns' => 'they/them',
                'email' => 'admin@corvallismusic.org',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Administrator of the Corvallis Music Collective. I help manage the community and love connecting musicians with each other.</p><p>Feel free to reach out if you have any questions about the collective or need help getting involved!</p>',
                'hometown' => 'Corvallis, OR',
                'links' => [
                    ['name' => 'CMC Website', 'url' => 'https://corvallismusic.org'],
                    ['name' => 'Instagram', 'url' => 'https://instagram.com/corvallismusic'],
                ],
                'contact' => new \App\Data\ContactData(
                    visibility: 'public',
                    email: 'admin@corvallismusic.org',
                    phone: '(541) 555-0100',
                    address: '123 Music Lane, Corvallis, OR 97330'
                ),
                'visibility' => 'public',
            ]
        );

        $user = $result['user'];
        $profile = $result['profile'];

        $user->assignRole('admin');
        $profile->attachTags(['Community Management', 'Event Planning', 'Music Education'], 'skill');
        $profile->attachTags(['Folk', 'Indie', 'Acoustic'], 'genre');
        $profile->flag('open_to_collaboration');
        $profile->flag('music_teacher');

        return $result;
    }

    /**
     * Create professional musician with full profile, tags, and flags
     */
    public static function createProfessionalMusician()
    {
        $result = static::createWithProfile(
            [
                'name' => 'Sarah Johnson',
                'pronouns' => 'she/her',
                'email' => 'sarah@example.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Professional vocalist and guitarist with 15+ years of experience. I specialize in jazz and blues but love exploring all genres.</p><p>Available for sessions, live performances, and collaboration. Currently working on my debut solo album.</p>',
                'hometown' => 'Portland, OR',
                'links' => [
                    ['name' => 'Spotify', 'url' => 'https://open.spotify.com/artist/sarahjohnson'],
                    ['name' => 'Bandcamp', 'url' => 'https://sarahjohnson.bandcamp.com'],
                    ['name' => 'Instagram', 'url' => 'https://instagram.com/sarahjmusic'],
                    ['name' => 'Website', 'url' => 'https://sarahjohnsonmusic.com'],
                ],
                'contact' => new \App\Data\ContactData(
                    visibility: 'public',
                    email: 'booking@sarahjohnsonmusic.com',
                    phone: '(503) 555-0123',
                ),
                'visibility' => 'public',
            ]
        );

        $profile = $result['profile'];
        $profile->attachTags(['Vocalist', 'Guitarist', 'Songwriter', 'Session Musician', 'Live Performance'], 'skill');
        $profile->attachTags(['Jazz', 'Blues', 'Soul', 'R&B'], 'genre');
        $profile->attachTags(['Ella Fitzgerald', 'B.B. King', 'Joni Mitchell', 'Nina Simone'], 'influence');
        $profile->flag('available_for_hire');
        $profile->flag('open_to_collaboration');

        return $result;
    }

    /**
     * Create beginner musician with basic profile and tags
     */
    public static function createBeginnerMusician()
    {
        $result = static::createWithProfile(
            [
                'name' => 'Alex Chen',
                'pronouns' => 'he/him',
                'email' => 'alex@example.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Just starting my musical journey! Learning guitar and always looking for people to play with.</p>',
                'hometown' => 'Corvallis, OR',
                'links' => [],
                'contact' => new \App\Data\ContactData(
                    visibility: 'members',
                    email: 'alex@example.com',
                ),
                'visibility' => 'members',
            ]
        );

        $profile = $result['profile'];
        $profile->attachTags(['Guitarist', 'Beginner'], 'skill');
        $profile->attachTags(['Rock', 'Pop'], 'genre');
        $profile->flag('looking_for_band');

        return $result;
    }

    /**
     * Create producer with members-only profile and tags
     */
    public static function createProducer()
    {
        $result = static::createWithProfile(
            [
                'name' => 'Jordan Martinez',
                'pronouns' => 'they/them',
                'email' => 'jordan@example.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Electronic music producer and sound engineer. I run a small home studio and love helping other artists bring their visions to life.</p><p>Specializing in ambient, experimental, and electronic genres but open to all styles.</p>',
                'hometown' => 'Eugene, OR',
                'links' => [
                    ['name' => 'SoundCloud', 'url' => 'https://soundcloud.com/jordanmartinez'],
                    ['name' => 'Bandcamp', 'url' => 'https://jordanmartinez.bandcamp.com'],
                ],
                'contact' => new \App\Data\ContactData(
                    visibility: 'members',
                    email: 'studio@jordanmartinez.com',
                    phone: '(541) 555-0456',
                ),
                'visibility' => 'members',
            ]
        );

        $profile = $result['profile'];
        $profile->attachTags(['Producer', 'Audio Engineer', 'Mixing', 'Mastering', 'Sound Design'], 'skill');
        $profile->attachTags(['Electronic', 'Ambient', 'Experimental', 'Techno'], 'genre');
        $profile->attachTags(['Brian Eno', 'Aphex Twin', 'Boards of Canada', 'Tim Hecker'], 'influence');
        $profile->flag('available_for_hire');
        $profile->flag('open_to_collaboration');

        return $result;
    }

    /**
     * Create private profile user
     */
    public static function createPrivateUser()
    {
        $result = static::createWithProfile(
            [
                'name' => 'Morgan Taylor',
                'email' => 'morgan@example.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Drummer looking to connect with local musicians for jam sessions.</p>',
                'links' => [],
                'contact' => new \App\Data\ContactData(
                    visibility: 'private',
                    email: 'morgan@example.com',
                ),
                'visibility' => 'private',
            ]
        );

        $profile = $result['profile'];
        $profile->attachTags(['Drummer', 'Percussion'], 'skill');
        $profile->attachTags(['Rock', 'Punk', 'Alternative'], 'genre');

        return $result;
    }

    /**
     * Create multi-instrumentalist with extensive profile
     */
    public static function createMultiInstrumentalist()
    {
        $result = static::createWithProfile(
            [
                'name' => 'River Thompson',
                'pronouns' => 'she/they',
                'email' => 'river@example.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'bio' => '<p>Multi-instrumentalist and composer with a passion for blending traditional and modern sounds. I play violin, piano, guitar, and various world instruments.</p><p>Currently scoring independent films and always interested in collaborative projects. I also teach private lessons in violin and piano.</p>',
                'hometown' => 'Salem, OR',
                'links' => [
                    ['name' => 'YouTube', 'url' => 'https://youtube.com/@riverthompson'],
                    ['name' => 'Instagram', 'url' => 'https://instagram.com/riverthompsonmusic'],
                    ['name' => 'Website', 'url' => 'https://riverthompsonmusic.com'],
                    ['name' => 'Spotify', 'url' => 'https://open.spotify.com/artist/riverthompson'],
                ],
                'contact' => new \App\Data\ContactData(
                    visibility: 'public',
                    email: 'contact@riverthompsonmusic.com',
                    phone: '(503) 555-0789',
                    address: 'Salem, OR (studio visits by appointment)'
                ),
                'visibility' => 'public',
            ]
        );

        $profile = $result['profile'];
        $profile->attachTags(['Violinist', 'Pianist', 'Guitarist', 'Composer', 'Music Teacher', 'Film Scoring'], 'skill');
        $profile->attachTags(['Classical', 'World', 'Cinematic', 'Folk', 'Experimental'], 'genre');
        $profile->attachTags(['Max Richter', 'Ólafur Arnalds', 'Nils Frahm', 'Hildegard Westerkamp'], 'influence');
        $profile->flag('music_teacher');
        $profile->flag('available_for_hire');
        $profile->flag('open_to_collaboration');

        return $result;
    }

    /**
     * Create random user with profile and tags for bulk seeding
     */
    public static function createRandomUser(array $profileOverrides = [])
    {
        $result = static::createWithProfile([], $profileOverrides);
        $profile = $result['profile'];

        // Add random skills tags
        $skills = [
            'Vocalist',
            'Guitarist',
            'Bassist',
            'Drummer',
            'Keyboardist',
            'Pianist',
            'Producer',
            'Songwriter',
            'Audio Engineer',
            'Mixing',
            'Mastering',
            'DJ',
            'Beat Making',
            'Sound Design',
            'Composer',
            'Arranger',
            'Violin',
            'Saxophone',
            'Trumpet',
            'Flute',
            'Harmonica',
            'Banjo',
            'Ukulele',
            'Mandolin',
            'Accordion',
            'Cello',
            'Double Bass',
            'Session Musician',
            'Live Performance',
            'Studio Recording',
            'Music Theory',
            'Improvisation',
            'Backup Vocals',
            'Lead Vocals',
        ];

        $numSkills = fake()->numberBetween(1, 6);
        $selectedSkills = fake()->randomElements($skills, $numSkills);
        foreach ($selectedSkills as $skill) {
            $profile->attachTag($skill, 'skill');
        }

        // Add random genre tags
        $genres = [
            'Rock',
            'Pop',
            'Jazz',
            'Blues',
            'Folk',
            'Country',
            'Classical',
            'Electronic',
            'Hip Hop',
            'R&B',
            'Soul',
            'Funk',
            'Reggae',
            'Punk',
            'Metal',
            'Alternative',
            'Indie',
            'Ambient',
            'House',
            'Techno',
            'Dubstep',
            'Drum & Bass',
            'Experimental',
            'World',
            'Latin',
            'Acoustic',
            'Singer-Songwriter',
            'Progressive',
            'Psychedelic',
            'Garage',
            'Grunge',
            'Ska',
            'Bluegrass',
        ];

        $numGenres = fake()->numberBetween(1, 5);
        $selectedGenres = fake()->randomElements($genres, $numGenres);
        foreach ($selectedGenres as $genre) {
            $profile->attachTag($genre, 'genre');
        }

        // Add random influence tags
        $influences = [
            'The Beatles',
            'Bob Dylan',
            'Miles Davis',
            'Jimi Hendrix',
            'Joni Mitchell',
            'Prince',
            'Stevie Wonder',
            'David Bowie',
            'Radiohead',
            'Nirvana',
            'Led Zeppelin',
            'Pink Floyd',
            'The Rolling Stones',
            'Johnny Cash',
            'Aretha Franklin',
            'John Coltrane',
            'Billie Holiday',
            'Elvis Presley',
            'The Clash',
            'Talking Heads',
            'Kraftwerk',
            'Aphex Twin',
            'Björk',
            'Tori Amos',
            'Fiona Apple',
            'Jeff Buckley',
            'Nick Drake',
            'Leonard Cohen',
            'Tom Waits',
            'Frank Zappa',
            'Captain Beefheart',
            'Velvet Underground',
            'Patti Smith',
            'Television',
            'Wire',
            'Can',
            'Brian Eno',
        ];

        $numInfluences = fake()->numberBetween(0, 4);
        if ($numInfluences > 0) {
            $selectedInfluences = fake()->randomElements($influences, $numInfluences);
            foreach ($selectedInfluences as $influence) {
                $profile->attachTag($influence, 'influence');
            }
        }

        // Add some random directory flags
        $availableFlags = ['open_to_collaboration', 'available_for_hire', 'looking_for_band', 'music_teacher'];
        $numFlags = fake()->numberBetween(0, 2);
        if ($numFlags > 0) {
            $selectedFlags = fake()->randomElements($availableFlags, $numFlags);
            foreach ($selectedFlags as $flag) {
                $profile->flag($flag);
            }
        }

        return $result;
    }

    /**
     * Create multiple random users with profiles and tags
     */
    public static function createRandomUsers(int $count, array $profileOverrides = [])
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = static::createRandomUser($profileOverrides);
        }

        return $users;
    }
}
