<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BandPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create band-related permissions
        $permissions = [
            // Basic band permissions
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
            'delete bands',
            'restore bands',
            'force delete bands',
            
            // Band member management
            'manage band members',
            'invite band members',
            'remove band members',
            'change member roles',
            'view band members',
            
            // Band ownership and administration
            'transfer band ownership',
            'promote to band admin',
            'demote band admin',
            
            // Band content management
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',
            
            // Advanced band permissions
            'view private bands',
            'moderate bands',
            'approve band creation',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create or update roles with band permissions
        $this->createAdminRole();
        $this->createModeratorRole();
        $this->createMemberRole();
        $this->createBandLeaderRole();
    }

    private function createAdminRole(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        
        // Admins get all band permissions
        $admin->givePermissionTo([
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
            'delete bands',
            'restore bands',
            'force delete bands',
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
            'view private bands',
            'moderate bands',
            'approve band creation',
        ]);
    }

    private function createModeratorRole(): void
    {
        $moderator = Role::firstOrCreate(['name' => 'moderator']);
        
        // Moderators get most band permissions except destructive ones
        $moderator->givePermissionTo([
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
            'manage band members',
            'invite band members',
            'remove band members',
            'view band members',
            'upload band media',
            'manage band settings',
            'manage band visibility',
            'manage band links',
            'manage band contact info',
            'view private bands',
            'moderate bands',
            'approve band creation',
        ]);
    }

    private function createMemberRole(): void
    {
        $member = Role::firstOrCreate(['name' => 'member']);
        
        // Regular members get basic permissions
        $member->givePermissionTo([
            'view bands',
            'create bands',
            'view band members',
        ]);
    }

    private function createBandLeaderRole(): void
    {
        $bandLeader = Role::firstOrCreate(['name' => 'band leader']);
        
        // Band leaders get enhanced permissions for band management
        $bandLeader->givePermissionTo([
            'view bands',
            'view any bands',
            'create bands',
            'update bands',
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
    }
}