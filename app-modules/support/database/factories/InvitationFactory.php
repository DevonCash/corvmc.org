<?php

namespace CorvMC\Support\Database\Factories;

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Support\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Support\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'inviter_id' => User::factory(),
            'user_id' => User::factory(),
            'invitable_type' => 'band',
            'invitable_id' => Band::factory(),
            'status' => 'pending',
            'data' => null,
            'responded_at' => null,
        ];
    }

    /**
     * Invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    /**
     * Invitation has been declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    /**
     * Self-initiated invitation (no inviter), e.g. event RSVP.
     */
    public function selfInitiated(): static
    {
        return $this->state(fn (array $attributes) => [
            'inviter_id' => null,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }
}
