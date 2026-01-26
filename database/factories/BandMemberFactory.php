<?php

namespace Database\Factories;

use CorvMC\Bands\Models\Band;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Bands\Models\BandMember>
 */
class BandMemberFactory extends Factory
{
    protected $model = \CorvMC\Bands\Models\BandMember::class;

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
            'role' => $this->faker->randomElement(['member', 'admin']),
            'position' => $this->faker->randomElement([
                'Lead Vocalist', 'Backing Vocalist', 'Lead Guitarist', 'Rhythm Guitarist',
                'Bassist', 'Drummer', 'Keyboardist', 'Pianist', 'Saxophonist',
            ]),
            'status' => 'active',
            'invited_at' => null,
        ];
    }

    /**
     * Create an invited (pending) band member.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'invited',
            'invited_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Create an admin band member.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
