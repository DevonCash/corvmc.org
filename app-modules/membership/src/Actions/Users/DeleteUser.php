<?php

namespace CorvMC\Membership\Actions\Users;

use App\Models\User;
use CorvMC\Membership\Notifications\UserDeactivatedNotification;
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
        $result = DB::transaction(function () use ($user) {
            // Cancel any future reservations
            $user->reservations()
                ->where('reserved_at', '>', now())
                ->update(['status' => 'cancelled']);

            // Soft delete the user
            return $user->delete();
        });

        if ($result) {
            // Send deactivation notification outside transaction
            try {
                $user->notify(new UserDeactivatedNotification);
            } catch (\Exception $e) {
                \Log::error('Failed to send user deactivated notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }
}
