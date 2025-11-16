<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUser
{
    use AsAction;

    /**
     * Update a user with validation and notifications.
     */
    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $originalData = $user->toArray();

            // Handle password update
            if (isset($data['password']) && ! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Handle role updates
            $roleNames = [];
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roleNames = \Spatie\Permission\Models\Role::whereIn('id', $data['roles'])
                    ->pluck('name')
                    ->toArray();
                unset($data['roles']);
            }

            // Update user
            $user->update($data);

            // Update roles if provided
            if (! empty($roleNames)) {
                $user->syncRoles($roleNames);
            }

            // Profile creation is handled automatically by the User model

            // Send update notification if significant changes
            $this->sendUpdateNotificationIfNeeded($user, $originalData, $data);

            return $user->fresh();
        });
    }

    /**
     * Send update notification if significant changes occurred.
     */
    private function sendUpdateNotificationIfNeeded(User $user, array $originalData, array $newData): void
    {
        $significantFields = ['email', 'name'];
        $hasSignificantChanges = false;

        foreach ($significantFields as $field) {
            if (isset($newData[$field]) && $originalData[$field] !== $newData[$field]) {
                $hasSignificantChanges = true;
                break;
            }
        }

        if ($hasSignificantChanges) {
            $user->notify(new UserUpdatedNotification($originalData, $newData));
        }
    }
}
