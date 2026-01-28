<?php

namespace Database\Factories;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Events\Models\TicketOrder>
 */
class TicketOrderFactory extends Factory
{
    protected $model = TicketOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 4);
        $unitPrice = config('ticketing.default_price', 1000);
        $subtotal = $unitPrice * $quantity;

        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'status' => TicketOrderStatus::Pending,
            'email' => $this->faker->email(),
            'name' => $this->faker->name(),
            'quantity' => $quantity,
            'unit_price' => Money::ofMinor($unitPrice, 'USD'),
            'subtotal' => Money::ofMinor($subtotal, 'USD'),
            'discount' => Money::ofMinor(0, 'USD'),
            'fees' => Money::ofMinor(0, 'USD'),
            'total' => Money::ofMinor($subtotal, 'USD'),
            'covers_fees' => false,
            'is_door_sale' => false,
        ];
    }

    /**
     * Configure the model as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketOrderStatus::Completed,
            'payment_method' => 'stripe',
            'completed_at' => now(),
        ]);
    }

    /**
     * Configure the model as refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketOrderStatus::Refunded,
            'payment_method' => 'stripe',
            'completed_at' => now()->subHours(2),
            'refunded_at' => now(),
        ]);
    }

    /**
     * Configure as a guest checkout (no user).
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Configure as a door sale.
     */
    public function doorSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_door_sale' => true,
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Apply sustaining member discount.
     */
    public function withDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity'] ?? 1;
            $basePrice = config('ticketing.default_price', 1000);
            $discountPercent = config('ticketing.sustaining_member_discount', 50);
            $unitPrice = (int) round($basePrice * (1 - $discountPercent / 100));
            $subtotal = $unitPrice * $quantity;
            $discountAmount = ($basePrice - $unitPrice) * $quantity;

            return [
                'unit_price' => Money::ofMinor($unitPrice, 'USD'),
                'subtotal' => Money::ofMinor($subtotal, 'USD'),
                'discount' => Money::ofMinor($discountAmount, 'USD'),
                'total' => Money::ofMinor($subtotal, 'USD'),
            ];
        });
    }
}
