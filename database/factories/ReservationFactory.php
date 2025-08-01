<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reservedAt = $this->faker->dateTimeBetween('+1 hour', '+1 month');
        $duration = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4, 6]); // hours
        $reservedUntil = (clone $reservedAt)->modify('+' . ($duration * 60) . ' minutes');

        return [
            'user_id' => User::factory(),
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'cancelled']),
            'cost' => function (array $attributes) use ($duration) {
                // Simple cost calculation - will be overridden by service when needed
                return $this->faker->boolean(30) ? 0 : $duration * ReservationService::HOURLY_RATE;
            },
            'hours_used' => $duration,
            'free_hours_used' => function (array $attributes) use ($duration) {
                // If cost is 0, it's all free hours
                return $attributes['cost'] == 0 ? $duration : 0;
            },
            'is_recurring' => $this->faker->boolean(20),
            'recurrence_pattern' => function (array $attributes) {
                return $attributes['is_recurring'] ? [
                    'weeks' => $this->faker->numberBetween(2, 8),
                    'interval' => $this->faker->randomElement([1, 2]),
                ] : null;
            },
            'notes' => $this->faker->boolean(40) ? $this->faker->sentence() : null,
        ];
    }

    /**
     * Indicate that the reservation is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the reservation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the reservation is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the reservation is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => 0,
        ]);
    }

    /**
     * Indicate that the reservation is recurring.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurrence_pattern' => [
                'weeks' => $this->faker->numberBetween(4, 12),
                'interval' => $this->faker->randomElement([1, 2]),
            ],
        ]);
    }

    /**
     * Create a reservation for a sustaining member.
     */
    public function forSustainingMember(): static
    {
        return $this->state(function (array $attributes) {
            $user = User::factory()->create();
            $user->assignRole('sustaining member');
            $hours = $attributes['hours_used'] ?? 2;
            
            return [
                'user_id' => $user->id,
                'cost' => 0, // Sustaining members often get free hours
                'free_hours_used' => $hours,
            ];
        });
    }

    /**
     * Create an upcoming reservation.
     */
    public function upcoming(): static
    {
        $reservedAt = $this->faker->dateTimeBetween('+1 hour', '+2 weeks');
        $duration = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4]);
        $reservedUntil = (clone $reservedAt)->modify('+' . ($duration * 60) . ' minutes');

        return $this->state(fn (array $attributes) => [
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Create a past reservation.
     */
    public function past(): static
    {
        $reservedAt = $this->faker->dateTimeBetween('-2 months', '-1 hour');
        $duration = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4]);
        $reservedUntil = (clone $reservedAt)->modify('+' . ($duration * 60) . ' minutes');

        return $this->state(fn (array $attributes) => [
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => 'confirmed',
        ]);
    }
}