<?php

namespace CorvMC\Events\Database\Factories;

use CorvMC\Events\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Events\Models\Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Venue',
            'address' => fake()->streetAddress(),
            'city' => 'Corvallis',
            'state' => 'OR',
            'zip' => '97330',
            'is_cmc' => false,
        ];
    }

    public function cmc(): static
    {
        return $this->state(fn () => [
            'name' => 'Corvallis Music Collective',
            'address' => '420 SW Washington Ave',
            'city' => 'Corvallis',
            'state' => 'OR',
            'zip' => '97333',
            'is_cmc' => true,
        ]);
    }
}
