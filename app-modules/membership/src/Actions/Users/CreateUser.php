<?php

namespace CorvMC\Membership\Actions\Users;

use App\Models\User;
use CorvMC\Membership\Notifications\UserCreatedNotification;
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
        $user = DB::transaction(function () use ($data) {
            // Extract role names if provided
            $roleNames = [];
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roleNames = \Spatie\Permission\Models\Role::whereIn('id', $data['roles'])
                    ->pluck('name')
                    ->toArray();
            }

            // Validate required data
            if (! isset($data['password']) || empty($data['password'])) {
                throw new \InvalidArgumentException('Password is required');
            }

            // Create user directly
            $userData = array_merge($data, [
                'password' => Hash::make($data['password']),
                'email_verified_at' => $data['email_verified_at'] ?? now(),
            ]);

            $user = User::create($userData);

            // Assign roles if provided
            if (! empty($roleNames)) {
                $user->assignRole($roleNames);
            }

            // Profile creation is handled automatically by the User model

            return $user;
        });

        // Send creation notification outside transaction
        try {
            $user->notify(new UserCreatedNotification);
        } catch (\Exception $e) {
            \Log::error('Failed to send user created notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}
