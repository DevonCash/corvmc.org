<?php

namespace CorvMC\Membership\Actions\Users;

use App\Models\User;
use CorvMC\Membership\Events\UserUpdated as UserUpdatedEvent;
use CorvMC\Membership\Notifications\UserUpdatedNotification;
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
        $originalData = $user->toArray();
        $loggedFields = ['name', 'email', 'staff_title', 'staff_type', 'show_on_about_page'];

        $user = DB::transaction(function () use ($user, $data) {
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

            return $user->fresh();
        });

        // Dispatch activity log event for tracked field changes
        $changedFields = array_keys(array_filter(
            array_intersect_key($user->toArray(), array_flip($loggedFields)),
            fn ($value, $key) => ($originalData[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if (! empty($changedFields)) {
            $oldValues = array_intersect_key($originalData, array_flip($changedFields));
            UserUpdatedEvent::dispatch($user, $changedFields, $oldValues);
        }

        // Send update notification if significant changes (outside transaction)
        $this->sendUpdateNotificationIfNeeded($user, $originalData, $data);

        return $user;
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
            try {
                $user->notify(new UserUpdatedNotification($originalData, $newData));
            } catch (\Exception $e) {
                \Log::error('Failed to send user updated notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
