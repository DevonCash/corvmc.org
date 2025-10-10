<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserDeactivatedNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteUser
{
    use AsAction;

    /**
     * Soft delete a user with notifications.
     */
    public function handle(User $user): bool
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
}
