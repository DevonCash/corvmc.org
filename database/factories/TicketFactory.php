<?php

namespace Database\Factories;

use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Events\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_order_id' => TicketOrder::factory(),
            'attendee_name' => $this->faker->name(),
            'attendee_email' => $this->faker->email(),
            'status' => TicketStatus::Valid,
        ];
    }

    /**
     * Configure the ticket as checked in.
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    /**
     * Configure the ticket as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Cancelled,
        ]);
    }
}
