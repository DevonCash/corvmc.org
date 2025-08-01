<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Permission::firstOrCreate(['name' => 'manage productions']);
        // Create roles using only the standard spatie/laravel-permission columns
        Role::firstOrCreate(['name' => 'admin']);

        Role::firstOrCreate(['name' => 'sustaining member']);

        Role::firstOrCreate(['name' => 'production manager'])
            ->syncPermissions(['manage productions']);

        Role::firstOrCreate(['name' => 'practice space manager']);

        Role::firstOrCreate(['name' => 'directory moderator']);


        $this->command->info('Created roles for permission system');
    }
}
