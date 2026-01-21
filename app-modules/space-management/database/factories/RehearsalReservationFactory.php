<?php

namespace CorvMC\SpaceManagement\Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;
use CorvMC\SpaceManagement\Enums\PaymentStatus;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\SpaceManagement\Models\RehearsalReservation>
 */
class RehearsalReservationFactory extends Factory
{
    protected $model = RehearsalReservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reservedAt = $this->faker->dateTimeBetween('+1 hour', '+1 month');
        $durationHours = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4, 6]);
        $reservedUntil = (clone $reservedAt)->modify("+{$durationHours} hours");

        return [
            // Don't set 'type' here - let the model's $attributes or boot() method handle it
            'reservable_type' => User::class,
            'reservable_id' => User::factory(),
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => $this->faker->randomElement([ReservationStatus::Scheduled, ReservationStatus::Confirmed, ReservationStatus::Cancelled]),
            'hours_used' => function (array $attributes) {
                $start = Carbon::parse($attributes['reserved_at']);
                $end = Carbon::parse($attributes['reserved_until']);

                return $start->diffInMinutes($end) / 60;
            },
            'cost' => function (array $attributes) {
                $hours = $attributes['hours_used'];

                return $this->faker->boolean(30) ? 0 : $hours * CalculateReservationCost::HOURLY_RATE;
            },
            'payment_status' => function (array $attributes) {
                return $attributes['cost'] == 0 ? PaymentStatus::NotApplicable : PaymentStatus::Unpaid;
            },
            'free_hours_used' => function (array $attributes) {
                return $attributes['cost'] == 0 ? $attributes['hours_used'] : 0;
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
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the reservation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Scheduled,
        ]);
    }

    /**
     * Indicate that the reservation is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
        ]);
    }

    /**
     * Indicate that the reservation is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => 0,
            'payment_status' => PaymentStatus::NotApplicable,
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
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'cost' => 0, // Sustaining members often get free hours
                'payment_status' => PaymentStatus::NotApplicable,
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
        $durationHours = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4]);
        $reservedUntil = (clone $reservedAt)->modify("+{$durationHours} hours");

        return $this->state(fn (array $attributes) => [
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    /**
     * Create a past reservation.
     */
    public function past(): static
    {
        $reservedAt = $this->faker->dateTimeBetween('-2 months', '-1 hour');
        $durationHours = $this->faker->randomElement([1, 1.5, 2, 2.5, 3, 4]);
        $reservedUntil = (clone $reservedAt)->modify("+{$durationHours} hours");

        return $this->state(fn (array $attributes) => [
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => ReservationStatus::Confirmed,
        ]);
    }
}
