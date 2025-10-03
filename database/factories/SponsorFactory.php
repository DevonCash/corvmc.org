<?php

namespace Database\Factories;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sponsor>
 */
class SponsorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tiers = [
            Sponsor::TIER_HARMONY,
            Sponsor::TIER_MELODY,
            Sponsor::TIER_RHYTHM,
            Sponsor::TIER_CRESCENDO,
        ];

        $tier = fake()->randomElement($tiers);

        return [
            'name' => fake()->company(),
            'tier' => $tier,
            'type' => Sponsor::TYPE_CASH,
            'description' => fake()->boolean(70) ? fake()->paragraph(2) : null,
            'website_url' => fake()->boolean(80) ? fake()->url() : null,
            'display_order' => fake()->numberBetween(0, 100),
            'is_active' => fake()->boolean(90),
            'started_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * Indicate that the sponsor is a Crescendo tier sponsor.
     */
    public function crescendo(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_CRESCENDO,
            'display_order' => fake()->numberBetween(0, 10),
        ]);
    }

    /**
     * Indicate that the sponsor is a Rhythm tier sponsor.
     */
    public function rhythm(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_RHYTHM,
            'display_order' => fake()->numberBetween(10, 30),
        ]);
    }

    /**
     * Indicate that the sponsor is a Melody tier sponsor.
     */
    public function melody(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_MELODY,
            'display_order' => fake()->numberBetween(30, 60),
        ]);
    }

    /**
     * Indicate that the sponsor is a Harmony tier sponsor.
     */
    public function harmony(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_HARMONY,
            'display_order' => fake()->numberBetween(60, 90),
        ]);
    }

    /**
     * Indicate that the sponsor is a fundraising partner.
     */
    public function fundraising(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_FUNDRAISING,
            'type' => Sponsor::TYPE_IN_KIND,
            'display_order' => fake()->numberBetween(90, 95),
        ]);
    }

    /**
     * Indicate that the sponsor is an in-kind service partner.
     */
    public function inKind(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Sponsor::TIER_IN_KIND,
            'type' => Sponsor::TYPE_IN_KIND,
            'display_order' => fake()->numberBetween(95, 100),
        ]);
    }

    /**
     * Indicate that the sponsor is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the sponsor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
