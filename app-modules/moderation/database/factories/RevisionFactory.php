<?php

namespace CorvMC\Moderation\Database\Factories;

use App\Models\MemberProfile;
use CorvMC\Moderation\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Revision>
 */
class RevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => MemberProfile::factory(),
            'original_data' => ['bio' => 'Original bio'],
            'proposed_changes' => ['bio' => $this->faker->paragraph()],
            'status' => $this->faker->randomElement([
                Revision::STATUS_PENDING,
                Revision::STATUS_APPROVED,
                Revision::STATUS_REJECTED,
            ]),
            'submitted_by_id' => User::factory(),
            'reviewed_by_id' => null,
            'reviewed_at' => null,
            'review_reason' => null,
            'revision_type' => 'update',
            'auto_approved' => false,
        ];
    }

    /**
     * Create a pending revision.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Revision::STATUS_PENDING,
            'reviewed_by_id' => null,
            'reviewed_at' => null,
            'auto_approved' => false,
        ]);
    }

    /**
     * Create an approved revision.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Revision::STATUS_APPROVED,
            'reviewed_by_id' => User::factory(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'review_reason' => 'Approved by moderator',
        ]);
    }

    /**
     * Create an auto-approved revision.
     */
    public function autoApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Revision::STATUS_APPROVED,
            'auto_approved' => true,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Create a rejected revision.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Revision::STATUS_REJECTED,
            'reviewed_by_id' => User::factory(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'review_reason' => 'Rejected for policy violation',
        ]);
    }
}
