<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-role {user : User ID or email} {role : Role name} {--remove : Remove the role instead of assigning it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign or remove a role from a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userIdentifier = $this->argument('user');
        $roleName = $this->argument('role');
        $remove = $this->option('remove');

        // Find user by ID or email
        $user = is_numeric($userIdentifier)
            ? User::find($userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return 1;
        }

        // Check if role exists
        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $this->error("Role not found: {$roleName}");

            return 1;
        }

        try {
            if ($remove) {
                if (! $user->hasRole($roleName)) {
                    $this->warn("User {$user->email} does not have role '{$roleName}'");

                    return 0;
                }

                $user->removeRole($roleName);
                $this->info("✓ Removed role '{$roleName}' from user {$user->email}");
            } else {
                if ($user->hasRole($roleName)) {
                    $this->warn("User {$user->email} already has role '{$roleName}'");

                    return 0;
                }

                $user->assignRole($roleName);
                $this->info("✓ Assigned role '{$roleName}' to user {$user->email}");
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }
}
