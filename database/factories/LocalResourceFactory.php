<?php

namespace Database\Factories;

use App\Models\LocalResource;
use App\Models\ResourceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocalResource>
 */
class LocalResourceFactory extends Factory
{
    protected $model = LocalResource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessNames = [
            'Guitar Center',
            'Sound Wave Studios',
            'Melody Music Shop',
            'Rhythm & Blues Records',
            'Acoustic Arts',
            'The Music Box',
            'Harmony Hall',
            'Corvallis School of Music',
            'Strings & Things',
            'Beat Street Audio',
        ];

        return [
            'resource_list_id' => ResourceList::factory(),
            'name' => fake()->randomElement($businessNames) . ' ' . fake()->unique()->numerify('###'),
            'description' => fake()->optional(0.7)->sentence(),
            'contact_name' => fake()->optional(0.6)->name(),
            'contact_email' => fake()->optional(0.7)->safeEmail(),
            'contact_phone' => fake()->optional(0.5)->phoneNumber(),
            'website' => fake()->optional(0.8)->url(),
            'address' => fake()->optional(0.6)->address(),
            'published_at' => fake()->optional(0.85)->dateTimeBetween('-1 year', 'now'),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    public function forList(ResourceList $list): static
    {
        return $this->state(fn (array $attributes) => [
            'resource_list_id' => $list->id,
        ]);
    }
}
