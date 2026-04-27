<?php

namespace CorvMC\Volunteering\Database\Factories;

use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\States\HourLogState\Approved;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\CheckedOut;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Pending;
use CorvMC\Volunteering\States\HourLogState\Rejected;
use CorvMC\Volunteering\States\HourLogState\Released;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Volunteering\Models\HourLog>
 */
class HourLogFactory extends Factory
{
    protected $model = HourLog::class;

    /**
     * Default: shift-based, interested status.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'position_id' => null,
            'status' => Interested::class,
            'started_at' => null,
            'ended_at' => null,
            'reviewed_by' => null,
            'notes' => null,
        ];
    }

    /**
     * Self-reported hour log (pending approval).
     */
    public function selfReported(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 month', '-1 hour');
        $endedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(1, 4).' hours');

        return $this->state(fn (array $attributes) => [
            'shift_id' => null,
            'position_id' => Position::factory(),
            'status' => Pending::class,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Interested in a shift (default status, explicit alias).
     */
    public function interested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Interested::class,
        ]);
    }

    /**
     * Confirmed for a shift.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Confirmed::class,
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Checked in to a shift.
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CheckedIn::class,
            'reviewed_by' => User::factory(),
            'started_at' => now(),
        ]);
    }

    /**
     * Checked out of a shift (completed, hours count).
     */
    public function checkedOut(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 month', '-2 hours');
        $endedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(1, 6).' hours');

        return $this->state(fn (array $attributes) => [
            'status' => CheckedOut::class,
            'reviewed_by' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Released from a shift.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Released::class,
            'reviewed_by' => User::factory(),
        ]);
    }

    /**
     * Self-reported and approved (hours count).
     */
    public function approved(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 month', '-2 hours');
        $endedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(1, 4).' hours');

        return $this->state(fn (array $attributes) => [
            'shift_id' => null,
            'position_id' => Position::factory(),
            'status' => Approved::class,
            'reviewed_by' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * Self-reported and rejected.
     */
    public function rejected(): static
    {
        $startedAt = fake()->dateTimeBetween('-1 month', '-2 hours');
        $endedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(1, 4).' hours');

        return $this->state(fn (array $attributes) => [
            'shift_id' => null,
            'position_id' => Position::factory(),
            'status' => Rejected::class,
            'reviewed_by' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'notes' => fake()->sentence(),
        ]);
    }
}
