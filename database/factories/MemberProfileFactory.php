<?php

namespace Database\Factories;

use App\Data\ContactData;
use App\Enums\Visibility;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberProfile>
 */
class MemberProfileFactory extends Factory
{
    protected $model = MemberProfile::class;

    /**
     * Create a user without events to prevent auto-profile creation
     */
    private function createUserWithoutProfile()
    {
        return User::withoutEvents(function () {
            return User::factory()->create();
        });
    }

    public function definition(): array
    {
        $platforms = [
            'Instagram' => 'https://instagram.com/'.fake()->userName(),
            'Facebook' => 'https://facebook.com/'.fake()->userName(),
            'Twitter' => 'https://twitter.com/'.fake()->userName(),
            'Bandcamp' => 'https://'.fake()->userName().'.bandcamp.com',
            'Spotify' => 'https://open.spotify.com/artist/'.fake()->bothify('?##?##?##?##?##'),
            'SoundCloud' => 'https://soundcloud.com/'.fake()->userName(),
            'YouTube' => 'https://youtube.com/@'.fake()->userName(),
            'TikTok' => 'https://tiktok.com/@'.fake()->userName(),
            'LinkedIn' => 'https://linkedin.com/in/'.fake()->userName(),
            'Website' => 'https://'.fake()->domainName(),
        ];

        // Generate 0-4 random links
        $numLinks = fake()->numberBetween(0, 4);
        $selectedPlatforms = fake()->randomElements(array_keys($platforms), $numLinks);
        $links = [];

        foreach ($selectedPlatforms as $platform) {
            $links[] = [
                'name' => $platform,
                'url' => $platforms[$platform],
            ];
        }

        return [
            'user_id' => $this->createUserWithoutProfile(),
            'bio' => fake()->boolean(80) ? $this->generateBio() : null,
            'hometown' => fake()->boolean(60) ? fake()->city().', '.fake()->stateAbbr() : null,
            'links' => $links,
            'contact' => new ContactData(
                visibility: fake()->randomElement(['private', 'members', 'public']),
                email: fake()->boolean(70) ? fake()->safeEmail() : null,
                phone: fake()->boolean(50) ? fake()->phoneNumber() : null,
                address: fake()->boolean(30) ? fake()->address() : null,
            ),
            'visibility' => fake()->randomElement([Visibility::Private, Visibility::Members, Visibility::Public]),
        ];
    }

    /**
     * Override make to handle user_id state properly
     */
    public function make($attributes = [], ?Model $parent = null)
    {
        // If user_id is explicitly provided in attributes, don't create a new user
        if (is_array($attributes) && isset($attributes['user_id'])) {
            $definition = $this->definition();
            $definition['user_id'] = $attributes['user_id'];
            unset($attributes['user_id']);

            return $this->state($definition)->make($attributes, $parent);
        }

        return parent::make($attributes, $parent);
    }

    public function configure()
    {
        return $this->afterCreating(function (MemberProfile $profile) {
            // Add skills tags
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

            // Add genre tags
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

            // Add influence tags
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
        });
    }

    private function generateBio(): string
    {
        $templates = [
            "I'm a {adjective} {musician_type} from {location}. I've been making music for {years} years and love exploring {genre1} and {genre2} sounds. When I'm not creating music, you can find me {hobby}.",

            "{musician_type} and {skill} based in {location}. My sound draws from {genre1}, {genre2}, and {genre3} influences. I'm passionate about {passion} and always looking to collaborate with like-minded artists.",

            "Multi-instrumentalist with a focus on {skill1} and {skill2}. I've been part of the {location} music scene for {years} years. My work spans {genre1} to {genre2}, always with an emphasis on {value}.",

            'Independent {musician_type} creating {adjective} {genre1} music. I believe in {philosophy} and love working with other artists to bring unique sounds to life. Based in {location} but always ready to travel for the right project.',

            "{adjective} songwriter and {skill} from {location}. My influences range from {influence1} to {influence2}, and I'm always experimenting with new sounds. Currently working on {project} and open to collaboration.",
        ];

        $replacements = [
            '{adjective}' => fake()->randomElement(['passionate', 'dedicated', 'versatile', 'creative', 'innovative', 'soulful', 'dynamic']),
            '{musician_type}' => fake()->randomElement(['musician', 'artist', 'songwriter', 'producer', 'performer', 'composer']),
            '{location}' => fake()->city(),
            '{years}' => fake()->numberBetween(3, 25),
            '{genre1}' => fake()->randomElement(['jazz', 'rock', 'folk', 'electronic', 'classical', 'blues', 'pop', 'indie']),
            '{genre2}' => fake()->randomElement(['ambient', 'punk', 'soul', 'country', 'experimental', 'world', 'reggae', 'metal']),
            '{genre3}' => fake()->randomElement(['hip hop', 'R&B', 'funk', 'progressive', 'acoustic', 'psychedelic']),
            '{hobby}' => fake()->randomElement(['hiking', 'reading', 'cooking', 'painting', 'traveling', 'photography', 'gardening']),
            '{skill}' => fake()->randomElement(['producer', 'vocalist', 'guitarist', 'drummer', 'keyboardist', 'sound engineer']),
            '{skill1}' => fake()->randomElement(['guitar', 'piano', 'vocals', 'bass', 'drums', 'production']),
            '{skill2}' => fake()->randomElement(['songwriting', 'arrangement', 'mixing', 'composition', 'improvisation']),
            '{passion}' => fake()->randomElement(['authentic expression', 'musical storytelling', 'innovative sound design', 'collaborative creation', 'live performance']),
            '{value}' => fake()->randomElement(['authenticity', 'creativity', 'collaboration', 'innovation', 'emotional depth']),
            '{philosophy}' => fake()->randomElement(['music as universal language', 'the power of authentic expression', 'collaborative creativity', 'pushing musical boundaries']),
            '{project}' => fake()->randomElement(['my debut album', 'a new EP', 'some exciting collaborations', 'a concept album', 'live recordings']),
            '{influence1}' => fake()->randomElement(['Miles Davis', 'Joni Mitchell', 'The Beatles', 'Radiohead', 'Prince']),
            '{influence2}' => fake()->randomElement(['Aphex Twin', 'Johnny Cash', 'Björk', 'David Bowie', 'Nina Simone']),
        ];

        $template = fake()->randomElement($templates);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function withoutBio(): static
    {
        return $this->state(['bio' => null]);
    }

    /**
     * Create profile without auto-adding tags (for testing)
     */
    public function withoutTags(): static
    {
        return $this->afterCreating(function (MemberProfile $profile) {
            // Remove all tags that were added
            $profile->detachTags($profile->tags);
        });
    }

    public function private(): static
    {
        return $this->state([
            'visibility' => Visibility::Private,
            'contact' => new ContactData(visibility: 'private'),
        ]);
    }

    public function public(): static
    {
        return $this->state([
            'visibility' => Visibility::Public,
            'contact' => new ContactData(visibility: 'public'),
        ]);
    }

    public function membersOnly(): static
    {
        return $this->state([
            'visibility' => Visibility::Members,
            'contact' => new ContactData(visibility: 'members'),
        ]);
    }
}
