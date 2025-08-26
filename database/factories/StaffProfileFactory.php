<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['board', 'staff']);
        
        $boardTitles = [
            'Board President',
            'Board Vice President', 
            'Treasurer',
            'Secretary',
            'Board Member',
        ];
        
        $staffTitles = [
            'Operations Manager',
            'Program Coordinator',
            'Event Coordinator',
            'Facility Manager',
            'Marketing Coordinator',
            'Volunteer Coordinator',
        ];
        
        $title = $type === 'board' 
            ? fake()->randomElement($boardTitles)
            : fake()->randomElement($staffTitles);
            
        $socialPlatforms = ['website', 'linkedin', 'twitter', 'facebook', 'instagram', 'github'];
        $socialLinks = [];
        
        // Randomly add 0-3 social links
        $numLinks = fake()->numberBetween(0, 3);
        $selectedPlatforms = fake()->randomElements($socialPlatforms, $numLinks);
        
        foreach ($selectedPlatforms as $platform) {
            $socialLinks[] = [
                'platform' => $platform,
                'url' => match($platform) {
                    'website' => fake()->url(),
                    'linkedin' => 'https://linkedin.com/in/' . fake()->userName(),
                    'twitter' => 'https://twitter.com/' . fake()->userName(),
                    'facebook' => 'https://facebook.com/' . fake()->userName(),
                    'instagram' => 'https://instagram.com/' . fake()->userName(),
                    'github' => 'https://github.com/' . fake()->userName(),
                }
            ];
        }

        $name = fake()->name();
        $email = fake()->optional(0.6)->safeEmail();
        
        return [
            'user_id' => User::factory()->create([
                'name' => $name,
                'email' => $email ?: fake()->safeEmail(),
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ])->id,
            'name' => $name,
            'title' => fake()->optional(0.8)->passthrough($title), // 80% chance of having a title
            'bio' => fake()->optional(0.8)->paragraph(2),
            'type' => $type,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'email' => $email,
            'social_links' => empty($socialLinks) ? null : $socialLinks,
        ];
    }

    public function board(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'board',
            'title' => fake()->randomElement([
                'Board President',
                'Board Vice President',
                'Treasurer', 
                'Secretary',
                'Board Member',
            ]),
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'staff',
            'title' => fake()->randomElement([
                'Operations Manager',
                'Program Coordinator',
                'Event Coordinator',
                'Facility Manager',
                'Marketing Coordinator',
                'Volunteer Coordinator',
            ]),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
