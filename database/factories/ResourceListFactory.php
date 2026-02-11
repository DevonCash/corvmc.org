<?php

namespace Database\Factories;

use App\Models\ResourceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResourceList>
 */
class ResourceListFactory extends Factory
{
    protected $model = ResourceList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Music Shops',
            'Instrument Repair',
            'Recording Studios',
            'Rehearsal Spaces',
            'Merch Artists',
            'Music Teachers',
            'Venues',
            'Music Schools',
            'Sound Engineers',
            'Promoters',
        ];

        return [
            'name' => fake()->randomElement($categories),
            'description' => fake()->optional(0.8)->paragraph(),
            'display_order' => fake()->numberBetween(0, 100),
        ];
    }
}
