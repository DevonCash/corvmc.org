<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles are created for testing
        $this->setupRoles();
        
        // Seed essential data
        $this->seedEssentialData();
    }

    /**
     * Setup required roles for testing.
     */
    protected function setupRoles(): void
    {
        // Run the permission seeder to ensure all roles and permissions exist
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * Seed essential data for tests.
     */
    protected function seedEssentialData(): void
    {
        // Override in specific test classes if needed
    }

    /**
     * Create a sustaining member user for testing.
     */
    protected function createSustainingMember(array $attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        $user->assignRole('sustaining member');
        return $user;
    }

    /**
     * Create a band leader user for testing.
     */
    protected function createBandLeader(array $attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        $user->assignRole('band leader');
        return $user;
    }


    /**
     * Create a regular user (no special roles).
     */
    protected function createUser(array $attributes = [])
    {
        return \App\Models\User::factory()->create($attributes);
    }

    /**
     * Create a band with owner and optional members.
     */
    protected function createBand(array $attributes = [], $owner = null, array $members = [])
    {
        $owner = $owner ?: $this->createUser();
        
        $band = \App\Models\Band::factory()->create(array_merge([
            'owner_id' => $owner->id,
        ], $attributes));

        foreach ($members as $member) {
            $band->members()->attach($member->id, [
                'role' => 'member',
                'status' => 'active',
            ]);
        }

        return $band;
    }

    /**
     * Create a production (event/show).
     */
    protected function createProduction(array $attributes = [], $manager = null)
    {
        $manager = $manager ?: $this->createUser();
        
        return \App\Models\Production::factory()->create(array_merge([
            'manager_id' => $manager->id,
        ], $attributes));
    }

    /**
     * Create a reservation for practice space.
     */
    protected function createReservation(array $attributes = [], $user = null)
    {
        $user = $user ?: $this->createUser();
        
        return \App\Models\Reservation::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }

    /**
     * Create a transaction record.
     */
    protected function createTransaction(array $attributes = [], $user = null)
    {
        $user = $user ?: $this->createUser();
        
        return \App\Models\Transaction::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }
}
