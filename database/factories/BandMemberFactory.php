<?php

namespace Database\Factories;

use App\Models\Band;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BandMember>
 */
class BandMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'band_profile_id' => Band::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'role' => $this->faker->randomElement(['vocalist', 'guitarist', 'bassist', 'drummer', 'keyboardist']),
            'position' => $this->faker->randomElement(['lead', 'rhythm', 'backup']),
            'status' => $this->faker->randomElement(['active', 'inactive', 'pending']),
            'invited_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
