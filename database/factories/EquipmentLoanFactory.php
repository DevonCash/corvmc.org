<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\States\EquipmentLoan\Requested;
use App\States\EquipmentLoan\StaffPreparing;
use App\States\EquipmentLoan\ReadyForPickup;
use App\States\EquipmentLoan\CheckedOut;
use App\States\EquipmentLoan\Overdue;
use App\States\EquipmentLoan\DropoffScheduled;
use App\States\EquipmentLoan\StaffProcessingReturn;
use App\States\EquipmentLoan\Returned;
use App\States\EquipmentLoan\Cancelled;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentLoan>
 */
class EquipmentLoanFactory extends Factory
{
    protected $model = EquipmentLoan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reservedFrom = $this->faker->dateTimeBetween('now', '+7 days');
        $dueAt = $this->faker->dateTimeBetween($reservedFrom, '+14 days');
        
        return [
            'equipment_id' => Equipment::factory(),
            'borrower_id' => User::factory(),
            'reserved_from' => $reservedFrom,
            'checked_out_at' => null, // Only set for checked out loans
            'due_at' => $dueAt,
            'returned_at' => null,
            'state' => Requested::class,
            'condition_out' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor', 'needs_repair']),
            'condition_in' => null,
            'security_deposit' => $this->faker->randomFloat(2, 0, 200),
            'rental_fee' => $this->faker->randomFloat(2, 0, 50),
            'notes' => $this->faker->optional()->sentence(),
            'damage_notes' => null,
        ];
    }

    /**
     * Loan in requested state.
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => Requested::class,
            'checked_out_at' => null,
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan being prepared by staff.
     */
    public function staffPreparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => StaffPreparing::class,
            'checked_out_at' => null,
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan ready for pickup.
     */
    public function readyForPickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => ReadyForPickup::class,
            'checked_out_at' => now(),
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan that is currently checked out.
     */
    public function checkedOut(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('-30 days', 'now');
        $checkedOutAt = $this->faker->dateTimeBetween($reservedFrom, 'now');
        return $this->state(fn (array $attributes) => [
            'state' => CheckedOut::class,
            'reserved_from' => $reservedFrom,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween($checkedOutAt, '+14 days'),
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan that is overdue.
     */
    public function overdue(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('-30 days', '-8 days');
        $checkedOutAt = $this->faker->dateTimeBetween($reservedFrom, '-8 days');
        return $this->state(fn (array $attributes) => [
            'state' => Overdue::class,
            'reserved_from' => $reservedFrom,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan scheduled for dropoff.
     */
    public function dropoffScheduled(): static
    {
        $checkedOutAt = $this->faker->dateTimeBetween('-30 days', '-1 day');
        return $this->state(fn (array $attributes) => [
            'state' => DropoffScheduled::class,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween($checkedOutAt, '+14 days'),
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan being processed for return by staff.
     */
    public function staffProcessingReturn(): static
    {
        $checkedOutAt = $this->faker->dateTimeBetween('-30 days', '-1 day');
        return $this->state(fn (array $attributes) => [
            'state' => StaffProcessingReturn::class,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween($checkedOutAt, '+14 days'),
            'returned_at' => null,
            'condition_in' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor', 'needs_repair']),
        ]);
    }

    /**
     * Loan that has been returned.
     */
    public function returned(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('-30 days', '-7 days');
        $checkedOutAt = $this->faker->dateTimeBetween($reservedFrom, '-7 days');
        $returnedAt = $this->faker->dateTimeBetween($checkedOutAt, 'now');
        
        return $this->state(fn (array $attributes) => [
            'state' => Returned::class,
            'reserved_from' => $reservedFrom,
            'checked_out_at' => $checkedOutAt,
            'returned_at' => $returnedAt,
            'condition_in' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor', 'needs_repair']),
        ]);
    }

    /**
     * Loan that has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => Cancelled::class,
            'checked_out_at' => null,
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan where equipment was lost.
     */
    public function lost(): static
    {
        $checkedOutAt = $this->faker->dateTimeBetween('-30 days', '-1 day');
        return $this->state(fn (array $attributes) => [
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween($checkedOutAt, '+14 days'),
            'returned_at' => null,
            'condition_in' => null,
            'damage_notes' => 'Equipment reported lost by borrower',
        ]);
    }

    /**
     * Loan that is currently active (alias for checkedOut).
     */
    public function active(): static
    {
        return $this->checkedOut();
    }

    /**
     * Future reservation (reserved but not yet started).
     */
    public function futureReservation(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $dueAt = $this->faker->dateTimeBetween($reservedFrom, (clone $reservedFrom)->modify('+7 days'));
        
        return $this->state(fn (array $attributes) => [
            'state' => Requested::class,
            'reserved_from' => $reservedFrom,
            'checked_out_at' => null,
            'due_at' => $dueAt,
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Current reservation (period is active but not checked out yet).
     */
    public function currentReservation(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('-1 day', 'now');
        $dueAt = $this->faker->dateTimeBetween('now', '+7 days');
        
        return $this->state(fn (array $attributes) => [
            'state' => ReadyForPickup::class,
            'reserved_from' => $reservedFrom,
            'checked_out_at' => null,
            'due_at' => $dueAt,
            'returned_at' => null,
            'condition_in' => null,
        ]);
    }

    /**
     * Loan with damage reported.
     */
    public function withDamage(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_in' => $this->faker->randomElement(['fair', 'poor', 'needs_repair']),
            'damage_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Loan with high security deposit.
     */
    public function highDeposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'security_deposit' => $this->faker->randomFloat(2, 200, 500),
            'rental_fee' => $this->faker->randomFloat(2, 25, 100),
        ]);
    }

    /**
     * Free loan (no fees).
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'security_deposit' => 0,
            'rental_fee' => 0,
        ]);
    }

    /**
     * Long-term loan.
     */
    public function longTerm(): static
    {
        $reservedFrom = $this->faker->dateTimeBetween('-60 days', '-30 days');
        $checkedOutAt = $this->faker->dateTimeBetween($reservedFrom, '-30 days');
        
        return $this->state(fn (array $attributes) => [
            'reserved_from' => $reservedFrom,
            'checked_out_at' => $checkedOutAt,
            'due_at' => $this->faker->dateTimeBetween($checkedOutAt, '+90 days'),
        ]);
    }
}