<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->clearUserCaches($user);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->clearUserCaches($user);
    }

    /**
     * Handle the User "roles updated" event (when roles are attached/detached).
     */
    public function rolesUpdated(User $user): void
    {
        $this->clearUserCaches($user);
    }

    /**
     * Clear all caches related to a specific user.
     */
    private function clearUserCaches(User $user): void
    {
        // Clear user-specific dashboard caches
        Cache::forget("user_stats.{$user->id}");
        Cache::forget("user_activity.{$user->id}");

        // Clear caches that include this user in aggregations
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
    }
}
