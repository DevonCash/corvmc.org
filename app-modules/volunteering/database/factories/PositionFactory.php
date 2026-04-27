<?php

namespace CorvMC\Volunteering\Database\Factories;

use CorvMC\Volunteering\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\CorvMC\Volunteering\Models\Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $positions = [
            'Sound Person',
            'Door Volunteer',
            'Host',
            'Tech',
            'Worker',
            'Social Media Coordinator',
            'Grant Writer',
            'Newsletter Editor',
            'Setup Crew',
            'Cleanup Crew',
        ];

        return [
            'title' => fake()->unique()->randomElement($positions),
            'description' => fake()->boolean(70) ? fake()->paragraph(2) : null,
        ];
    }
}
