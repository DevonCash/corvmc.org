<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use NotificationService::scheduleCustom() instead
 * This action is maintained for backward compatibility only.
 * New code should use the NotificationService directly.
 */
class ScheduleCustomNotification
{
    use AsAction;

    /**
     * @deprecated Use NotificationService::scheduleCustom() instead
     */
    public function handle(User $user, $notification, ?Carbon $sendAt = null): bool
    {
        return app(NotificationService::class)->scheduleCustom($user, $notification, $sendAt);
    }
}
