<?php

namespace Database\Factories;

use App\Models\Band;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Band>
 */
class BandFactory extends Factory
{
    protected $model = Band::class;

    public function definition(): array
    {
        $bandTypes = ['Band', 'Duo', 'Trio', 'Quartet', 'Ensemble', 'Orchestra', 'Collective'];
        $adjectives = ['Electric', 'Midnight', 'Golden', 'Silver', 'Dark', 'Bright', 'Wild', 'Free', 'Lost', 'Found'];
        $nouns = ['Hearts', 'Souls', 'Dreams', 'Echoes', 'Shadows', 'Lights', 'Stars', 'Moons', 'Rivers', 'Mountains'];

        // Generate unique band name by adding a random suffix to avoid collisions
        $baseName = $this->faker->randomElement($adjectives).' '.$this->faker->randomElement($nouns);
        $bandName = $baseName . ' ' . $this->faker->unique()->numberBetween(1000, 9999);

        return [
            'name' => $bandName,
            'bio' => $this->faker->paragraphs(3, true),
            'hometown' => $this->faker->city().', '.$this->faker->stateAbbr(),
            'owner_id' => 1, // Will be overridden by seeder
            'visibility' => $this->faker->randomElement(['public', 'members', 'private']),
            'links' => $this->generateLinks(),
            'contact' => $this->generateContact(),
        ];
    }

    private function generateLinks(): array
    {
        $links = [];
        $platforms = [
            'website' => 'https://www.'.$this->faker->domainName(),
            'spotify' => 'https://open.spotify.com/artist/'.$this->faker->uuid(),
            'bandcamp' => 'https://'.$this->faker->slug().'.bandcamp.com',
            'youtube' => 'https://youtube.com/@'.$this->faker->slug(),
            'instagram' => 'https://instagram.com/'.$this->faker->slug(),
            'facebook' => 'https://facebook.com/'.$this->faker->slug(),
            'soundcloud' => 'https://soundcloud.com/'.$this->faker->slug(),
        ];

        // Add 2-4 random links
        $selectedPlatforms = $this->faker->randomElements(array_keys($platforms), $this->faker->numberBetween(2, 4));

        foreach ($selectedPlatforms as $platform) {
            $links[] = [
                'name' => ucfirst($platform),
                'url' => $platforms[$platform],
            ];
        }

        return $links;
    }

    private function generateContact(): array
    {
        return [
            'email' => $this->faker->boolean(70) ? $this->faker->safeEmail() : null,
            'phone' => $this->faker->boolean(40) ? $this->faker->phoneNumber() : null,
            'address' => $this->faker->boolean(30) ? $this->faker->address() : null,
            'visibility' => $this->faker->randomElement(['public', 'members', 'private']),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    public function withGenres(?array $genres = null): static
    {
        return $this->afterCreating(function (Band $band) use ($genres) {
            $defaultGenres = ['Rock', 'Pop', 'Jazz', 'Blues', 'Folk', 'Electronic', 'Hip Hop', 'Country', 'Classical', 'Punk', 'Metal', 'Indie', 'Alternative'];
            $selectedGenres = $genres ?? $this->faker->randomElements($defaultGenres, $this->faker->numberBetween(1, 4));

            foreach ($selectedGenres as $genre) {
                $band->attachTag($genre, 'genre');
            }
        });
    }

    public function withInfluences(?array $influences = null): static
    {
        return $this->afterCreating(function (Band $band) use ($influences) {
            $defaultInfluences = [
                'The Beatles', 'Led Zeppelin', 'Pink Floyd', 'Queen', 'The Rolling Stones',
                'Bob Dylan', 'Joni Mitchell', 'Miles Davis', 'John Coltrane', 'Radiohead',
                'Nirvana', 'Pearl Jam', 'Red Hot Chili Peppers', 'Foo Fighters', 'Arctic Monkeys',
            ];
            $selectedInfluences = $influences ?? $this->faker->randomElements($defaultInfluences, $this->faker->numberBetween(2, 6));

            foreach ($selectedInfluences as $influence) {
                $band->attachTag($influence, 'influence');
            }
        });
    }
}
