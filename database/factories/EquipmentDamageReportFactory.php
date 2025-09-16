<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentDamageReport;
use App\Models\EquipmentLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentDamageReport>
 */
class EquipmentDamageReportFactory extends Factory
{
    protected $model = EquipmentDamageReport::class;

    public function definition(): array
    {
        $discoveredAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $severity = $this->faker->randomElement(['low', 'medium', 'high', 'critical']);
        $status = $this->faker->randomElement(['reported', 'in_progress', 'waiting_parts', 'completed', 'cancelled']);
        
        // More likely to be completed if older
        if ($discoveredAt < now()->subMonths(2)) {
            $status = $this->faker->randomElement(['completed', 'cancelled']);
        }

        return [
            'equipment_id' => Equipment::factory(),
            'equipment_loan_id' => $this->faker->boolean(60) ? EquipmentLoan::factory() : null,
            'reported_by_id' => User::factory(),
            'assigned_to_id' => $this->faker->boolean(70) ? User::factory() : null,
            'title' => $this->generateDamageTitle(),
            'description' => $this->generateDamageDescription(),
            'severity' => $severity,
            'status' => $status,
            'priority' => $this->getPriorityForSeverity($severity),
            'estimated_cost' => $this->faker->boolean(60) ? $this->faker->numberBetween(1000, 50000) : null, // 10-500 dollars in cents
            'actual_cost' => $status === 'completed' ? $this->faker->numberBetween(500, 60000) : null, // 5-600 dollars in cents
            'repair_notes' => $status === 'completed' ? $this->faker->paragraph() : null,
            'discovered_at' => $discoveredAt,
            'started_at' => in_array($status, ['in_progress', 'waiting_parts', 'completed']) 
                ? $this->faker->dateTimeBetween($discoveredAt, 'now') 
                : null,
            'completed_at' => $status === 'completed' 
                ? $this->faker->dateTimeBetween($discoveredAt, 'now') 
                : null,
        ];
    }

    private function generateDamageTitle(): string
    {
        $damageTypes = [
            'Scratched surface',
            'Broken knob',
            'Loose connection',
            'Cracked case',
            'Missing screw',
            'Worn frets',
            'Dead pickup',
            'Buzzing sound',
            'Sticky key',
            'Dented body',
            'Frayed cable',
            'Broken string',
            'Faulty switch',
            'Damaged finish',
            'Loose tuning peg'
        ];

        return $this->faker->randomElement($damageTypes);
    }

    private function generateDamageDescription(): string
    {
        $descriptions = [
            'Equipment shows signs of normal wear but needs attention.',
            'Damage discovered during routine inspection before loan.',
            'Member reported issue during equipment return process.',
            'Cosmetic damage that does not affect functionality.',
            'Functional issue that prevents proper operation.',
            'Minor repair needed to restore equipment to good condition.',
            'Significant damage requiring professional repair.',
            'Safety concern that requires immediate attention.',
        ];

        return $this->faker->randomElement($descriptions) . ' ' . $this->faker->sentence();
    }

    private function getPriorityForSeverity(string $severity): string
    {
        return match($severity) {
            'low' => $this->faker->randomElement(['low', 'normal']),
            'medium' => $this->faker->randomElement(['normal', 'high']),
            'high' => $this->faker->randomElement(['high', 'urgent']),
            'critical' => 'urgent',
            default => 'normal'
        };
    }

    public function reported(): static
    {
        return $this->state([
            'status' => 'reported',
            'started_at' => null,
            'completed_at' => null,
            'actual_cost' => null,
            'repair_notes' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => 'in_progress',
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at' => null,
            'actual_cost' => null,
        ]);
    }

    public function completed(): static
    {
        $startedAt = $this->faker->dateTimeBetween('-1 month', '-1 week');
        
        return $this->state([
            'status' => 'completed',
            'started_at' => $startedAt,
            'completed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
            'actual_cost' => $this->faker->numberBetween(1000, 30000), // 10-300 dollars in cents
            'repair_notes' => $this->faker->paragraph(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state([
            'severity' => $this->faker->randomElement(['high', 'critical']),
            'priority' => $this->faker->randomElement(['high', 'urgent']),
        ]);
    }

    public function withLoan(): static
    {
        return $this->state([
            'equipment_loan_id' => EquipmentLoan::factory(),
        ]);
    }

    public function withoutLoan(): static
    {
        return $this->state([
            'equipment_loan_id' => null,
        ]);
    }
}