<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cached checksum — computed once per process.
     */
    protected static ?string $snapshotChecksum = null;

    /**
     * Whether the snapshot has been restored for this process.
     */
    protected static bool $snapshotRestored = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSnapshotDatabase();
    }

    // ──────────────────────────────────────────────────────────
    //  Snapshot + transaction lifecycle
    // ──────────────────────────────────────────────────────────

    /**
     * Once per process: restore the snapshot into the working DB.
     * Every test: wrap in a transaction and roll back afterward.
     */
    protected function useSnapshotDatabase(): void
    {
        $dbPath = $this->getDatabasePath();

        // Point the SQLite connection at this process's working file
        $default = config('database.default');
        config()->set("database.connections.{$default}.database", $dbPath);

        if (! static::$snapshotRestored) {
            $checksum = $this->getSnapshotChecksum();
            $snapshotPath = $this->getSnapshotPath($checksum);

            if (! file_exists($snapshotPath)) {
                $this->buildSnapshot($snapshotPath, $dbPath);
            }

            // Purge any existing connection, copy snapshot, then reconnect
            $this->app->make('db')->purge();
            copy($snapshotPath, $dbPath);

            // Clean up this process's working file on exit
            register_shutdown_function(function () use ($dbPath) {
                @unlink($dbPath);
            });

            static::$snapshotRestored = true;
        }

        // Reset the permission cache so it loads fresh from this connection
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->beginTransaction();
    }

    /**
     * Wrap the test in a transaction. Rolled back in beforeApplicationDestroyed.
     */
    protected function beginTransaction(): void
    {
        $database = $this->app->make('db');
        $name = config('database.default');
        $connection = $database->connection($name);

        $this->app->instance(
            'db.transactions',
            $transactionsManager = new DatabaseTransactionsManager([$name])
        );
        $connection->setTransactionManager($transactionsManager);

        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();
        $connection->beginTransaction();
        $connection->setEventDispatcher($dispatcher);

        $this->beforeApplicationDestroyed(function () use ($database, $name) {
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->rollBack();
            $connection->setEventDispatcher($dispatcher);
        });
    }

    // ──────────────────────────────────────────────────────────
    //  Snapshot building
    // ──────────────────────────────────────────────────────────

    /**
     * Run migrations + seeds into the working DB, save as snapshot,
     * and clean up stale snapshots from previous migration states.
     */
    protected function buildSnapshot(string $snapshotPath, string $dbPath): void
    {
        $lockPath = base_path('database/.snapshot.lock');
        $lockHandle = fopen($lockPath, 'c');
        flock($lockHandle, LOCK_EX);

        try {
            // Another process may have built it while we waited
            if (file_exists($snapshotPath)) {
                return;
            }

            if (! file_exists($dbPath)) {
                touch($dbPath);
            }

            $this->artisan('migrate:fresh');
            $this->seedSnapshot();

            // Flush to disk before copying
            $this->app->make('db')->purge();

            copy($dbPath, $snapshotPath);

            $this->cleanStaleSnapshots($snapshotPath);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Seeds baked into every snapshot.
     */
    protected function seedSnapshot(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seedSettings();
    }

    protected function seedSettings(): void
    {
        $migrator = app(SettingsMigrator::class);

        if (! $migrator->exists('reservation.buffer_minutes')) {
            $migrator->add('reservation.buffer_minutes', 0);
        }
        if (! $migrator->exists('reservation.default_event_setup_minutes')) {
            $migrator->add('reservation.default_event_setup_minutes', 120);
        }
        if (! $migrator->exists('reservation.default_event_teardown_minutes')) {
            $migrator->add('reservation.default_event_teardown_minutes', 60);
        }
        if (! $migrator->exists('equipment.enable_equipment_features')) {
            $migrator->add('equipment.enable_equipment_features', false);
        }
        if (! $migrator->exists('equipment.enable_rental_features')) {
            $migrator->add('equipment.enable_rental_features', false);
        }
    }

    /**
     * Remove snapshot files that don't match the current checksum.
     */
    protected function cleanStaleSnapshots(string $currentSnapshotPath): void
    {
        foreach (glob(base_path('database/testing.*.sqlite')) as $file) {
            if ($file !== $currentSnapshotPath) {
                @unlink($file);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Paths and checksums
    // ──────────────────────────────────────────────────────────

    /**
     * Absolute path to the working database for this process.
     * Parallel processes get unique files via TEST_TOKEN.
     */
    protected function getDatabasePath(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? null;

        $filename = $token
            ? "testing_{$token}.sqlite"
            : 'testing.sqlite';

        return base_path("database/{$filename}");
    }

    protected function getSnapshotPath(string $checksum): string
    {
        return base_path("database/testing.{$checksum}.sqlite");
    }

    /**
     * Checksum of all migration files, settings migrations, and the seeder.
     * Cached per process since files don't change mid-run.
     */
    protected function getSnapshotChecksum(): string
    {
        if (static::$snapshotChecksum !== null) {
            return static::$snapshotChecksum;
        }

        $dirs = [
            base_path('database/migrations'),
            base_path('database/settings'),
        ];

        foreach (glob(base_path('app-modules/*/database/migrations')) as $dir) {
            $dirs[] = $dir;
        }

        $hash = '';

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.php') as $file) {
                $hash .= $file . ':' . filemtime($file) . ':' . filesize($file) . "\n";
            }
        }

        $seederPath = base_path('database/seeders/PermissionSeeder.php');
        if (file_exists($seederPath)) {
            $hash .= $seederPath . ':' . filemtime($seederPath) . ':' . filesize($seederPath) . "\n";
        }

        return static::$snapshotChecksum = substr(md5($hash), 0, 12);
    }
}
