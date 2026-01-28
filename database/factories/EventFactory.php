<?php

namespace Database\Factories;

use CorvMC\Events\Enums\EventStatus;
use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Events\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('now', '+6 months');
        $endTime = (clone $startTime)->modify('+'.$this->faker->numberBetween(90, 240).' minutes');
        $doorsTime = (clone $startTime)->modify('-30 minutes');

        return [
            'title' => $this->faker->randomElement([
                'Open Mic Night',
                'Summer Music Festival',
                'Local Artists Showcase',
                'Acoustic Evening',
                'Rock Battle',
                'Jazz in the Park',
                'Singer-Songwriter Night',
                'Band Competition',
                'Music & Poetry',
                'Indie Rock Showcase',
                'Folk Festival',
                'Electronic Music Night',
                'Tribute Band Night',
                'Unplugged Sessions',
                'Community Concert',
            ]),
            'description' => $this->faker->paragraphs(3, true),
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'doors_datetime' => $doorsTime,
            'venue_id' => function () {
                // 70% CMC, 30% external venue
                if ($this->faker->boolean(70)) {
                    return Venue::cmc()->first()?->id ?? 1;
                }

                return Venue::external()->inRandomOrder()->first()?->id ?? 1;
            },
            'event_link' => $this->faker->boolean(60) ? $this->faker->randomElement([
                'https://eventbrite.com/event/'.$this->faker->numerify('############'),
                'https://ticketmaster.com/event/'.$this->faker->numerify('##########'),
                'https://brownpapertickets.com/event/'.$this->faker->numerify('#######'),
                'https://corvallismusiccollective.org/tickets/'.$this->faker->slug,
                'https://facebook.com/events/'.$this->faker->numerify('###############'),
                'https://tixr.com/groups/'.$this->faker->numerify('######').'/'.$this->faker->numerify('########'),
            ]) : null,
            'ticket_url' => null, // Use event_link instead
            'ticket_price' => function (array $attributes) {
                if (! $attributes['event_link']) {
                    return null;
                }

                if ($this->faker->boolean(20)) {
                    return null;
                }

                return $this->faker->randomElement([
                    5.00, 8.00, 10.00, 12.00, 15.00, 18.00, 20.00, 25.00, 30.00,
                ]);
            },
            'status' => EventStatus::Scheduled,
            'visibility' => Visibility::Public,
            'published_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'organizer_id' => null, // Staff events by default
            'event_type' => null,
            'distance_from_corvallis' => null,
            'trust_points' => 0,
            'auto_approved' => false,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Scheduled,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the event is upcoming.
     */
    public function upcoming(): static
    {
        $startTime = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $endTime = (clone $startTime)->modify('+'.$this->faker->numberBetween(90, 240).' minutes');
        $doorsTime = (clone $startTime)->modify('-30 minutes');

        return $this->state(fn (array $attributes) => [
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'doors_datetime' => $doorsTime,
            'status' => EventStatus::Scheduled,
            'published_at' => $this->faker->dateTimeBetween('-2 weeks', 'now'),
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        $startTime = $this->faker->dateTimeBetween('-6 months', '-1 week');
        $endTime = (clone $startTime)->modify('+'.$this->faker->numberBetween(90, 240).' minutes');
        $doorsTime = (clone $startTime)->modify('-30 minutes');

        return $this->state(fn (array $attributes) => [
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'doors_datetime' => $doorsTime,
            'status' => EventStatus::Scheduled,
            'published_at' => $this->faker->dateTimeBetween('-7 months', $startTime),
        ]);
    }

    /**
     * Enable native ticketing for this event.
     */
    public function withNativeTicketing(?int $quantity = null, ?int $sold = null): static
    {
        return $this->state(fn (array $attributes) => [
            'ticketing_enabled' => true,
            'ticket_quantity' => $quantity ?? $this->faker->randomElement([50, 75, 100, 150, 200]),
            'tickets_sold' => $sold ?? 0,
            'ticket_price_override' => $this->faker->boolean(30)
                ? $this->faker->randomElement([500, 800, 1200, 1500, 2000])
                : null,
            // Clear external ticket URL when using native ticketing
            'event_link' => null,
            'ticket_url' => null,
            'ticket_price' => null,
        ]);
    }

    /**
     * Configure the model factory after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Event $event) {
            if ($event->hasTickets() && $this->faker->boolean(40)) {
                $event->setNotaflof(true);
            }
        });
    }
}
