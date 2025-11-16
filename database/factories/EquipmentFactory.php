<?php

namespace Database\Factories;

use App\Data\ContactData;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['guitar', 'bass', 'amplifier', 'microphone', 'percussion', 'recording', 'specialty'];
        $conditions = ['excellent', 'good', 'fair', 'poor', 'needs_repair'];
        $acquisitionTypes = ['donated', 'loaned_to_us', 'purchased'];
        $statuses = ['available', 'checked_out', 'maintenance', 'retired'];

        return [
            'name' => $this->faker->randomElement([
                'Fender Stratocaster',
                'Gibson Les Paul',
                'Fender Jazz Bass',
                'Marshall Stack',
                'Shure SM57',
                'Pearl Export Kit',
                'Roland TD-17',
                'Yamaha Acoustic',
                'Boss Delay Pedal',
                'Audio Interface',
            ]),
            'type' => $this->faker->randomElement($types),
            'brand' => $this->faker->randomElement([
                'Fender', 'Gibson', 'Marshall', 'Shure', 'Roland',
                'Yamaha', 'Boss', 'Pearl', 'Zildjian', 'Behringer',
            ]),
            'model' => $this->faker->bothify('??-####'),
            'serial_number' => $this->faker->optional()->bothify('##??######'),
            'description' => $this->faker->optional()->sentence(),
            'condition' => $this->faker->randomElement($conditions),
            'acquisition_type' => $this->faker->randomElement($acquisitionTypes),
            'provider_id' => $this->faker->optional(0.7)->randomElement(User::pluck('id')->toArray() ?: [null]),
            'provider_contact' => $this->faker->optional(0.3)->passthrough(
                new ContactData(
                    email: $this->faker->email(),
                    phone: $this->faker->phoneNumber(),
                    address: $this->faker->address()
                )
            ),
            'acquisition_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'return_due_date' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 year'),
            'acquisition_notes' => $this->faker->optional()->sentence(),
            'ownership_status' => $this->faker->randomElement(['cmc_owned', 'on_loan_to_cmc', 'returned_to_owner']),
            'status' => $this->faker->randomElement($statuses),
            'estimated_value' => $this->faker->optional(0.8)->randomFloat(2, 50, 2000),
            'location' => $this->faker->optional()->randomElement([
                'Main storage', 'Practice room', 'Office', 'Stage area', 'Maintenance area',
            ]),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Equipment that is donated.
     */
    public function donated(): static
    {
        return $this->state(fn (array $attributes) => [
            'acquisition_type' => 'donated',
            'ownership_status' => 'cmc_owned',
            'return_due_date' => null,
        ]);
    }

    /**
     * Equipment that is loaned to CMC.
     */
    public function loanedToCmc(): static
    {
        return $this->state(fn (array $attributes) => [
            'acquisition_type' => 'loaned_to_us',
            'ownership_status' => 'on_loan_to_cmc',
            'return_due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Equipment that is available for checkout.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'ownership_status' => 'cmc_owned',
            'condition' => $this->faker->randomElement(['excellent', 'good']),
        ]);
    }

    /**
     * Equipment that is currently checked out.
     */
    public function checkedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'checked_out',
            'ownership_status' => 'cmc_owned',
        ]);
    }

    /**
     * Equipment needing maintenance.
     */
    public function needsMaintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
            'condition' => $this->faker->randomElement(['fair', 'poor', 'needs_repair']),
        ]);
    }

    /**
     * High-value equipment.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_value' => $this->faker->randomFloat(2, 1000, 5000),
        ]);
    }

    /**
     * Equipment with external provider contact.
     */
    public function externalProvider(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => null,
            'provider_contact' => new ContactData(
                email: $this->faker->email(),
                phone: $this->faker->phoneNumber(),
                address: $this->faker->address()
            ),
        ]);
    }
}
