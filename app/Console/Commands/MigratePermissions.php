<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MigratePermissions extends Command
{
    protected $signature = 'permissions:migrate {--dry-run : Show what would be changed without making changes} {--force : Force migration without confirmation}';

    protected $description = 'Migrate permissions after permission structure changes';

    public function handle()
    {
        $this->info('🔐 Permission Migration Tool');
        $this->line('===========================');

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        // Clear cached permissions
        if (!$this->option('dry-run')) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            $this->info('✓ Cleared permission cache');
        } else {
            $this->line('→ Would clear permission cache');
        }

        // Get current state
        $currentPermissions = Permission::pluck('name')->toArray();
        $currentRoles = Role::with('permissions')->get();

        // Define new permission structure (from seeder)
        $newPermissions = $this->getNewPermissions();
        $newRolePermissions = $this->getNewRolePermissions();

        $this->line('');
        $this->info('📊 Current State Analysis');
        $this->line('Current permissions: ' . count($currentPermissions));
        $this->line('New permissions: ' . count($newPermissions));
        $this->line('Current roles: ' . $currentRoles->count());

        // Analyze changes
        $permissionsToAdd = array_diff($newPermissions, $currentPermissions);
        $permissionsToRemove = array_diff($currentPermissions, $newPermissions);

        $this->line('');
        $this->info('🔄 Changes to Apply');

        if (count($permissionsToAdd) > 0) {
            $this->line('Permissions to add (' . count($permissionsToAdd) . '):');
            foreach ($permissionsToAdd as $permission) {
                $this->line('  + ' . $permission);
            }
        }

        if (count($permissionsToRemove) > 0) {
            $this->warn('Permissions to remove (' . count($permissionsToRemove) . '):');
            foreach ($permissionsToRemove as $permission) {
                $this->line('  - ' . $permission);
            }
        }

        if (count($permissionsToAdd) === 0 && count($permissionsToRemove) === 0) {
            $this->info('✓ No permission changes needed');
        }

        // Show role permission changes
        $this->line('');
        $this->info('👥 Role Permission Updates');
        foreach ($currentRoles as $role) {
            $currentRolePermissions = $role->permissions->pluck('name')->toArray();
            $newRolePerms = $newRolePermissions[$role->name] ?? [];
            
            $toAdd = array_diff($newRolePerms, $currentRolePermissions);
            $toRemove = array_diff($currentRolePermissions, $newRolePerms);
            
            if (count($toAdd) > 0 || count($toRemove) > 0) {
                $this->line('Role: ' . $role->name);
                if (count($toAdd) > 0) {
                    foreach ($toAdd as $perm) {
                        $this->line('  + ' . $perm);
                    }
                }
                if (count($toRemove) > 0) {
                    foreach ($toRemove as $perm) {
                        $this->line('  - ' . $perm);
                    }
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->line('');
            $this->info('🔍 Dry run complete - no changes made');
            return;
        }

        // Confirmation
        if (!$this->option('force')) {
            $this->line('');
            if (!$this->confirm('Do you want to apply these changes?')) {
                $this->info('Migration cancelled');
                return;
            }
        }

        // Apply changes
        $this->line('');
        $this->info('🚀 Applying Changes');

        // Add new permissions
        foreach ($permissionsToAdd as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $this->line('✓ Added permission: ' . $permission);
        }

        // Remove old permissions
        foreach ($permissionsToRemove as $permission) {
            $perm = Permission::where('name', $permission)->first();
            if ($perm) {
                $perm->delete();
                $this->line('✓ Removed permission: ' . $permission);
            }
        }

        // Update role permissions
        foreach ($newRolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
            $this->line('✓ Updated role permissions: ' . $roleName);
        }

        // Clear cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->line('');
        $this->info('✅ Permission migration completed successfully!');
    }

    private function getNewPermissions(): array
    {
        return [
            // User Management
            'view users',
            'create users',
            'update users',
            'delete users',
            'restore users',
            'invite users',
            'update user roles',

            // Member Profile Management
            'view private member profiles',
            'update member profiles',
            'delete member profiles',
            'restore member profiles',

            // Band Management
            'view all bands', // Renamed from 'view bands'
            'view any bands', // New permission for basic band viewing
            'create bands',
            'update bands',
            'delete bands',
            'restore bands',
            'force delete bands',
            'view private bands',
            'moderate bands',
            'approve band creation',

            // Band Membership
            'manage band members',
            'invite band members',
            'remove band members',
            'change member roles',
            'view band members',
            'transfer band ownership',
            'promote to band admin',
            'demote band admin',

            // Band Content & Settings
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',

            // Production/Events Management
            'view productions',
            'manage productions',

            // Practice Space Management
            'view reservations',
            'delete reservations',
            'restore reservations',
            'manage practice space',

            // Financial Management
            'view transactions',
        ];
    }

    private function getNewRolePermissions(): array
    {
        return [
            'admin' => [
                // All permissions
                'view users', 'create users', 'update users', 'delete users', 'restore users', 'invite users', 'update user roles',
                'view private member profiles', 'update member profiles', 'delete member profiles', 'restore member profiles',
                'view all bands', 'view any bands', 'create bands', 'update bands', 'delete bands', 'restore bands', 'force delete bands',
                'view private bands', 'moderate bands', 'approve band creation',
                'manage band members', 'invite band members', 'remove band members', 'change member roles', 'view band members',
                'transfer band ownership', 'promote to band admin', 'demote band admin',
                'upload band media', 'manage band settings', 'manage band visibility', 'manage band links', 'manage band contact info',
                'view productions', 'manage productions',
                'view reservations', 'delete reservations', 'restore reservations', 'manage practice space',
                'view transactions',
            ],
            'moderator' => [
                'view users', 'invite users',
                'view all bands', 'view any bands', 'view private bands', 'create bands', 'update bands', 'moderate bands', 'approve band creation',
                'manage band members', 'invite band members', 'remove band members', 'view band members',
                'upload band media', 'manage band settings', 'manage band visibility', 'manage band links', 'manage band contact info',
                'view private member profiles',
            ],
            'production manager' => [
                'view productions', 'manage productions',
                'view any bands', 'create bands', 'view band members',
            ],
            'practice space manager' => [
                'view reservations', 'delete reservations', 'restore reservations', 'manage practice space',
                'view any bands', 'create bands', 'view band members',
            ],
            'directory moderator' => [
                'view private member profiles', 'update member profiles', 'delete member profiles', 'restore member profiles',
                'view all bands', 'view any bands', 'view private bands', 'moderate bands', 'view band members', 'manage band visibility',
                'create bands',
            ],
            'band leader' => [
                'view any bands', 'view all bands', 'create bands', 'update bands', 'delete bands', 'restore bands', 'view private bands',
                'manage band members', 'invite band members', 'remove band members', 'change member roles', 'view band members',
                'transfer band ownership', 'promote to band admin', 'demote band admin',
                'upload band media', 'manage band settings', 'manage band visibility', 'manage band links', 'manage band contact info',
            ],
            'sustaining member' => [
                'view any bands', 'create bands', 'view band members',
            ],
            'member' => [
                'view any bands', 'create bands', 'view band members',
            ],
        ];
    }
}