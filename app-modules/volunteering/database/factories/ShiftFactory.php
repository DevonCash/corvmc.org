<?php

namespace CorvMC\Volunteering\Database\Factories;

use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Volunteering\Models\Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 day', '+3 months');
        $endAt = (clone $startAt)->modify('+'.fake()->numberBetween(2, 6).' hours');

        return [
            'position_id' => Position::factory(),
            'event_id' => null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'capacity' => fake()->numberBetween(1, 5),
        ];
    }

    /**
     * Shift linked to a specific event.
     */
    public function forEvent(int $eventId): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $eventId,
        ]);
    }

    /**
     * Shift that has already passed.
     */
    public function past(): static
    {
        $startAt = fake()->dateTimeBetween('-3 months', '-1 day');
        $endAt = (clone $startAt)->modify('+'.fake()->numberBetween(2, 6).' hours');

        return $this->state(fn (array $attributes) => [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }

    /**
     * Shift happening right now.
     */
    public function happeningNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(3),
        ]);
    }
}
