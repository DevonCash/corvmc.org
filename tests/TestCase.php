<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles are created for testing
        $this->setupRoles();
    }

    /**
     * Setup required roles for testing.
     */
    protected function setupRoles(): void
    {
        // Run the permission seeder to ensure all roles and permissions exist
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }
}
