<?php

namespace Database\Factories;

use App\Data\LocationData;
use App\Enums\EventStatus;
use App\Enums\ModerationStatus;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
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
            'location' => $this->faker->boolean(30)
                ? LocationData::external($this->faker->randomElement([
                    'The Underground - 123 Main St, Corvallis, OR 97330',
                    'City Music Hall - 456 Oak Ave, Corvallis, OR 97330',
                    'Riverside Amphitheater - 789 River Rd, Corvallis, OR 97333',
                    'The Corner Stage - 321 2nd St, Corvallis, OR 97330',
                    'Main Street Venue - 654 Main St, Corvallis, OR 97330',
                    'Park Pavilion - Avery Park, Corvallis, OR 97330',
                    'Community Center - 2121 NW Kings Blvd, Corvallis, OR 97330',
                    'The Music Box - 987 Monroe Ave, Corvallis, OR 97330',
                ]))
                : LocationData::cmc(),
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
            'moderation_status' => ModerationStatus::Approved,
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
            'moderation_status' => ModerationStatus::Approved,
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
            'moderation_status' => ModerationStatus::Approved,
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
            'moderation_status' => ModerationStatus::Approved,
            'published_at' => $this->faker->dateTimeBetween('-7 months', $startTime),
        ]);
    }

    /**
     * Configure the model factory after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Event $event) {
            if ($event->hasTickets() && $this->faker->boolean(40)) {
                $event->setNotaflof(true);
            }
        });
    }
}
