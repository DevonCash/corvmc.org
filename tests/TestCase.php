<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed settings values required for tests
        $this->seedSettings();

        // Ensure roles are created for testing
        $this->setupRoles();
    }

    /**
     * Seed required settings values for tests.
     */
    protected function seedSettings(): void
    {
        $migrator = app(SettingsMigrator::class);

        // Reservation settings
        if (! $migrator->exists('reservation.buffer_minutes')) {
            $migrator->add('reservation.buffer_minutes', 0);
        }
        if (! $migrator->exists('reservation.default_event_setup_minutes')) {
            $migrator->add('reservation.default_event_setup_minutes', 120);
        }
        if (! $migrator->exists('reservation.default_event_teardown_minutes')) {
            $migrator->add('reservation.default_event_teardown_minutes', 60);
        }
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
