<?php

namespace Database\Factories;

use App\Models\CommunityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommunityEvent>
 */
class CommunityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            CommunityEvent::TYPE_PERFORMANCE,
            CommunityEvent::TYPE_WORKSHOP,
            CommunityEvent::TYPE_OPEN_MIC,
            CommunityEvent::TYPE_COLLABORATIVE_SHOW,
            CommunityEvent::TYPE_ALBUM_RELEASE,
        ];

        $venues = [
            ['name' => 'The Beanery', 'address' => '500 SW 2nd St, Corvallis, OR 97333'],
            ['name' => 'Bombs Away Cafe', 'address' => '2527 NW Monroe Ave, Corvallis, OR 97330'],
            ['name' => 'Local Boyz Hawaiian Cafe', 'address' => '1425 NE 3rd St, Corvallis, OR 97330'],
            ['name' => 'Whiteside Theatre', 'address' => '361 SW Madison Ave, Corvallis, OR 97333'],
            ['name' => 'Crystal Lake Sports Bar', 'address' => '2020 N 3rd St, Corvallis, OR 97330'],
            ['name' => 'McMenamins High Street Brewery', 'address' => '1243 High St, Eugene, OR 97401'],
            ['name' => 'WOW Hall', 'address' => '291 W 8th Ave, Eugene, OR 97401'],
        ];

        $venue = $this->faker->randomElement($venues);
        $startTime = $this->faker->dateTimeBetween('now', '+3 months');
        $endTime = (clone $startTime)->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return [
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->optional(0.8)->paragraphs(2, true),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'venue_name' => $venue['name'],
            'venue_address' => $venue['address'],
            'event_type' => $this->faker->randomElement($eventTypes),
            'status' => $this->faker->randomElement([
                CommunityEvent::STATUS_PENDING,
                CommunityEvent::STATUS_APPROVED,
                CommunityEvent::STATUS_APPROVED,
                CommunityEvent::STATUS_APPROVED, // Weight towards approved
            ]),
            'visibility' => $this->faker->randomElement([
                CommunityEvent::VISIBILITY_PUBLIC,
                CommunityEvent::VISIBILITY_PUBLIC,
                CommunityEvent::VISIBILITY_MEMBERS_ONLY, // Weight towards public
            ]),
            'published_at' => function (array $attributes) {
                return $attributes['status'] === CommunityEvent::STATUS_APPROVED 
                    ? $this->faker->dateTimeBetween('-1 month', 'now') 
                    : null;
            },
            'organizer_id' => User::factory(),
            'distance_from_corvallis' => $this->faker->randomFloat(2, 0, 90), // 0-90 minutes
            'ticket_url' => $this->faker->optional(0.3)->url(),
            'ticket_price' => $this->faker->optional(0.4)->randomFloat(2, 5, 50),
        ];
    }

    /**
     * Create an event that's pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityEvent::STATUS_PENDING,
            'published_at' => null,
        ]);
    }

    /**
     * Create an approved and published event.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityEvent::STATUS_APPROVED,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Create a local event (close to Corvallis).
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'distance_from_corvallis' => $this->faker->randomFloat(2, 0, 30), // 0-30 minutes
        ]);
    }

    /**
     * Create an upcoming event.
     */
    public function upcoming(): static
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+2 months');
        $endTime = (clone $startTime)->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Create an event with tickets.
     */
    public function withTickets(): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_url' => $this->faker->url(),
            'ticket_price' => $this->faker->randomFloat(2, 10, 75),
        ]);
    }
}
