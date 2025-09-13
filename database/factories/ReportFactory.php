<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Production;
use App\Models\MemberProfile;
use App\Models\Band;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to Production to avoid Band foreign key issues
        $production = Production::factory()->create();
        
        $reasons = ['inappropriate_content', 'spam', 'misleading_info', 'harassment', 'policy_violation'];
        $reason = fake()->randomElement($reasons);
        
        return [
            'reportable_type' => Production::class,
            'reportable_id' => $production->id,
            'reported_by_id' => User::factory()->create()->id,
            'reason' => $reason,
            'custom_reason' => $reason === 'other' ? fake()->sentence() : null,
            'status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'resolved_by_id' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);
    }

    public function upheld(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'upheld',
            'resolved_by_id' => User::factory()->create()->id,
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dismissed',
            'resolved_by_id' => User::factory()->create()->id,
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'escalated',
            'resolved_by_id' => User::factory()->create()->id,
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }

    public function forProduction(): static
    {
        return $this->state(function (array $attributes) {
            $production = Production::factory()->create();
            return [
                'reportable_type' => Production::class,
                'reportable_id' => $production->id,
            ];
        });
    }

    public function forMemberProfile(): static
    {
        return $this->state(function (array $attributes) {
            $profile = MemberProfile::factory()->create();
            return [
                'reportable_type' => MemberProfile::class,
                'reportable_id' => $profile->id,
            ];
        });
    }

    public function forBand(): static
    {
        return $this->state(function (array $attributes) {
            $band = Band::factory()->create();
            return [
                'reportable_type' => Band::class,
                'reportable_id' => $band->id,
            ];
        });
    }
}