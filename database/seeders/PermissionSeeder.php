<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $this->createPermissions();

        // Create roles and assign permissions
        $this->createRoles();

        $this->command->info('Created comprehensive permission system with roles');
    }

    private function createPermissions(): void
    {
        $permissions = [
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

            // Band Management
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
            'delete bands',
            'restore bands',
            'force delete bands',
            'view private bands',
            'moderate bands',
            'approve band creation',

            // Content Revision Management
            'view revisions',
            'approve revisions',
            'reject revisions',

            // Staff Profile Management
            'manage staff profiles',

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
            'manage events',
            'manage productions',

            // Practice Space Management
            'view reservations',
            'delete reservations',
            'restore reservations',
            'manage practice space',

            // Credit Management
            'manage credits',

            // Financial Management
            'view transactions',

            // Member Profile Management (continued)
            'delete member profiles',
            'restore member profiles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    private function createRoles(): void
    {
        // Admin - Full system access
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Moderator - Content moderation and member support
        $moderator = Role::firstOrCreate(['name' => 'moderator']);
        $moderator->syncPermissions([
            'view users',
            'invite users',
            'view any bands',
            'view private bands',
            'create bands',
            'update bands',
            'moderate bands',
            'approve band creation',
            'manage band members',
            'invite band members',
            'remove band members',
            'view band members',
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',
            'view private member profiles',
            'view revisions',
            'approve revisions',
            'reject revisions',
            'manage staff profiles',
        ]);

        // Production Manager - Event and show management
        $productionManager = Role::firstOrCreate(['name' => 'production manager']);
        $productionManager->syncPermissions([
            'view productions',
            'manage events',
            'manage productions',
            'view bands',
            'create bands',
            'view band members',
        ]);

        // Practice Space Manager - Practice space and booking management
        $practiceSpaceManager = Role::firstOrCreate(['name' => 'practice space manager']);
        $practiceSpaceManager->syncPermissions([
            'view reservations',
            'delete reservations',
            'restore reservations',
            'manage practice space',
            'manage credits',
            'view users',
            'view bands',
            'create bands',
            'view band members',
        ]);

        // Equipment Manager - Equipment lending library management
        $equipmentManager = Role::firstOrCreate(['name' => 'equipment manager']);
        $equipmentManager->syncPermissions([
            'view users',
            'view bands',
        ]);

        // Directory Moderator - Member and band profile oversight
        $directoryModerator = Role::firstOrCreate(['name' => 'directory moderator']);
        $directoryModerator->syncPermissions([
            'view private member profiles',
            'update member profiles',
            'delete member profiles',
            'restore member profiles',
            'view any bands',
            'view private bands',
            'moderate bands',
            'view band members',
            'manage band visibility',
            'view bands',
            'create bands',
        ]);

        // Band Leader - Enhanced band management for active band leaders
        $bandLeader = Role::firstOrCreate(['name' => 'band leader']);
        $bandLeader->syncPermissions([
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
            'delete bands',
            'restore bands',
            'view private bands',
            'manage band members',
            'invite band members',
            'remove band members',
            'change member roles',
            'view band members',
            'transfer band ownership',
            'promote to band admin',
            'demote band admin',
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',
        ]);

        // Staff - General staff access for kiosk and staff panel
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions([
            'view users',
            'view bands',
            'view productions',
            'view reservations',
        ]);

        // Sustaining Member - Enhanced features for paying members
        $sustainingMember = Role::firstOrCreate(['name' => 'sustaining member']);
        $sustainingMember->syncPermissions([
            'view bands',
            'create bands',
            'view band members',
        ]);

        // Member - Basic authenticated user
        $member = Role::firstOrCreate(['name' => 'member']);
        $member->syncPermissions([
            'view bands',
            'create bands',
            'view band members',
        ]);
    }
}
