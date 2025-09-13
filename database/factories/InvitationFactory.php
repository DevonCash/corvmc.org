<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'message' => 'Join me at Corvallis Music Collective!',
            'inviter_id' => \App\Models\User::factory(),
            'token' => \Illuminate\Support\Str::random(32),
            'expires_at' => now()->addWeeks(1),
            'last_sent_at' => now(),
        ];
    }
}
