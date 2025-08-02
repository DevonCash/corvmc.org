<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => $this->faker->uuid(),
            'email' => $this->faker->email(),
            'amount' => $this->faker->randomFloat(2, 5, 100),
            'currency' => 'USD',
            'type' => $this->faker->randomElement(['one-time', 'recurring']),
            'response' => [
                'status' => 'completed',
                'payment_method' => $this->faker->randomElement(['credit_card', 'bank_transfer', 'paypal']),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Indicate that the transaction is recurring.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'recurring',
            'amount' => $this->faker->randomFloat(2, 10, 50), // Sustaining member amounts
        ]);
    }

    /**
     * Indicate that the transaction is one-time.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'one-time',
        ]);
    }

    /**
     * Create a sustaining member level donation.
     */
    public function sustainingLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'recurring',
            'amount' => $this->faker->randomFloat(2, 15, 100), // Above $10 threshold
        ]);
    }

    /**
     * Create a transaction for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $user->email,
        ]);
    }
}
