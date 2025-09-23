<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserCreatedNotification;
use App\Notifications\UserUpdatedNotification;
use App\Notifications\UserDeactivatedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new user directly.
     */
    public function createUser(array $data): User
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

    /**
     * Update a user with validation and notifications.
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $originalData = $user->toArray();

            // Handle password update
            if (isset($data['password']) && !empty($data['password'])) {
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
            if (!empty($roleNames)) {
                $user->syncRoles($roleNames);
            }

            // Profile creation is handled automatically by the User model

            // Send update notification if significant changes
            $this->sendUpdateNotificationIfNeeded($user, $originalData, $data);

            return $user->fresh();
        });
    }

    /**
     * Soft delete a user with notifications.
     */
    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            // Cancel any future reservations
            $user->reservations()
                ->where('reserved_at', '>', now())
                ->update(['status' => 'cancelled']);

            // Soft delete the user
            $result = $user->delete();

            if ($result) {
                // Send deactivation notification
                $user->notify(new UserDeactivatedNotification());
            }

            return $result;
        });
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restoreUser(User $user): bool
    {
        return $user->restore();
    }

    /**
     * Force delete a user permanently.
     */
    public function forceDeleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            // Clean up related data
            $user->reservations()->forceDelete();
            $user->profile?->delete();

            return $user->forceDelete();
        });
    }

    /**
     * Get paginated users with filters.
     */
    public function getUsers(array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = User::with(['roles', 'profile']);

        // Apply filters
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['active'])) {
            if ($filters['active']) {
                $query->whereNull('deleted_at');
            } else {
                $query->onlyTrashed();
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get user statistics.
     */
    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::whereNull('deleted_at')->count(),
            'deactivated_users' => User::onlyTrashed()->count(),
            'users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'sustaining_members' => User::whereHas('roles', function ($q) {
                $q->where('name', 'sustaining member');
            })->count(),
        ];
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdateUsers(array $userIds, array $data): int
    {
        return DB::transaction(function () use ($userIds, $data) {
            $updatedCount = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $this->updateUser($user, $data);
                    $updatedCount++;
                }
            }

            return $updatedCount;
        });
    }

    /**
     * Bulk delete users.
     */
    public function bulkDeleteUsers(array $userIds): int
    {
        return DB::transaction(function () use ($userIds) {
            $deletedCount = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $this->deleteUser($user);
                    $deletedCount++;
                }
            }

            return $deletedCount;
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
