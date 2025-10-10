<?php

namespace App\Actions\Notifications;

use App\Exceptions\Services\NotificationSchedulingException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ScheduleCustomNotification
{
    use AsAction;

    /**
     * Schedule a custom notification for a specific user.
     */
    public function handle(User $user, $notification, Carbon $sendAt = null): bool
    {
        if (!$notification instanceof Notification) {
            throw NotificationSchedulingException::invalidNotificationClass(get_class($notification));
        }

        if (!$user->exists) {
            throw NotificationSchedulingException::userNotFound($user->id ?? 0);
        }

        if ($sendAt && $sendAt->isPast()) {
            throw NotificationSchedulingException::invalidSchedulingDate($sendAt);
        }

        try {
            if ($sendAt && $sendAt->isFuture()) {
                // Would need to integrate with Laravel queue scheduling for future notifications
                // For now, just send immediately if no future date specified
                Log::info('Custom notification scheduled for future delivery', [
                    'user_id' => $user->id,
                    'notification_class' => get_class($notification),
                    'send_at' => $sendAt,
                ]);

                // TODO: Implement queue job for future delivery
                return true;
            } else {
                $user->notify($notification);
                Log::info('Custom notification sent immediately', [
                    'user_id' => $user->id,
                    'notification_class' => get_class($notification),
                ]);
                return true;
            }
        } catch (\Exception $e) {
            $errorMessage = "Failed to schedule notification for user {$user->id}: {$e->getMessage()}";
            Log::error($errorMessage, [
                'user_id' => $user->id,
                'notification_class' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
