<?php

namespace App\Console\Commands;

use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateBandMembershipsToPermissions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:band-permissions 
                            {--dry-run : Show what would be migrated without making changes}
                            {--clean : Clean existing band permissions before migrating}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing band memberships to Spatie team-scoped permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Migrating Band Memberships to Scoped Permissions');
        $this->line('===============================================');

        $dryRun = $this->option('dry-run');
        $clean = $this->option('clean');

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        // Clean existing band permissions if requested
        if ($clean && ! $dryRun) {
            $this->cleanExistingBandPermissions();
        }

        // Get all active band memberships
        $memberships = BandMember::with(['band', 'user'])
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->get();

        $this->info("📊 Found {$memberships->count()} active band memberships to migrate");
        $this->line('');

        $migrated = 0;
        $skipped = 0;

        foreach ($memberships as $membership) {
            $result = $this->migrateMembership($membership, $dryRun);

            if ($result) {
                $migrated++;
            } else {
                $skipped++;
            }
        }

        $this->line('');
        $this->info('✅ Migration Summary:');
        $this->line("   Migrated: {$migrated}");
        $this->line("   Skipped: {$skipped}");

        if ($dryRun) {
            $this->warn('   No actual changes made (dry-run mode)');
        }

        return 0;
    }

    private function cleanExistingBandPermissions(): void
    {
        $this->info('🧹 Cleaning existing band-scoped permissions...');

        $bandScopedPermissions = [
            'update band',
            'manage band members',
            'invite band members',
            'remove band members',
            'change member roles',
            'view band members',
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',
            'delete band',
            'restore band',
        ];

        foreach (Band::all() as $band) {
            foreach (User::all() as $user) {
                foreach ($bandScopedPermissions as $permission) {
                    if ($user->hasPermissionTo($permission, $band)) {
                        $user->revokePermissionTo($permission, $band);
                    }
                }
            }
        }

        $this->line('   ✓ Cleaned existing permissions');
    }

    private function migrateMembership(BandMember $membership, bool $dryRun): bool
    {
        if (! $membership->user || ! $membership->band) {
            $this->warn("   ⚠️  Skipping membership #{$membership->id} - missing user or band");

            return false;
        }

        $user = $membership->user;
        $band = $membership->band;
        $role = $membership->role;

        $permissions = $this->getPermissionsForRole($role);

        if (empty($permissions)) {
            $this->warn("   ⚠️  Skipping membership #{$membership->id} - unknown role: {$role}");

            return false;
        }

        $actionText = $dryRun ? 'Would grant' : 'Granting';
        $this->line("   {$actionText} {$role} permissions to {$user->name} for band '{$band->name}':");

        foreach ($permissions as $permission) {
            if ($dryRun) {
                $this->line("      → Would grant '{$permission}' permission");
            } else {
                try {
                    $user->givePermissionTo($permission, $band);
                    $this->line("      ✓ Granted '{$permission}' permission");
                } catch (\Exception $e) {
                    $this->error("      ✗ Failed to grant '{$permission}': ".$e->getMessage());
                }
            }
        }

        return true;
    }

    private function getPermissionsForRole(string $role): array
    {
        return match ($role) {
            'owner' => [
                'update band',
                'manage band members',
                'invite band members',
                'remove band members',
                'change member roles',
                'view band members',
                'upload band media',
                'manage band settings',
                'manage band visibility',
                'manage band links',
                'manage band contact info',
                'delete band',
                'restore band',
            ],
            'admin' => [
                'update band',
                'manage band members',
                'invite band members',
                'remove band members',
                'view band members',
                'upload band media',
                'manage band settings',
                'manage band visibility',
                'manage band links',
                'manage band contact info',
            ],
            'member' => [
                'view band members',
            ],
            default => [],
        };
    }
}
