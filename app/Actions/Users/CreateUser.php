<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateUser
{
    use AsAction;

    /**
     * Create a new user directly.
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Extract role names if provided
            $roleNames = [];
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roleNames = \Spatie\Permission\Models\Role::whereIn('id', $data['roles'])
                    ->pluck('name')
                    ->toArray();
            }

            // Validate required data
            if (!isset($data['password']) || empty($data['password'])) {
                throw new \InvalidArgumentException('Password is required');
            }

            // Create user directly
            $userData = array_merge($data, [
                'password' => Hash::make($data['password']),
                'email_verified_at' => $data['email_verified_at'] ?? now(),
            ]);

            $user = User::create($userData);

            // Assign roles if provided
            if (!empty($roleNames)) {
                $user->assignRole($roleNames);
            }

            // Profile creation is handled automatically by the User model

            // Send creation notification
            $user->notify(new UserCreatedNotification());

            return $user;
        });
    }
}
