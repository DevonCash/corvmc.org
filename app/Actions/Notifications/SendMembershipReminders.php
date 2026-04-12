<?php

namespace App\Actions\Notifications;

use App\Services\NotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use NotificationService::sendMembershipReminders() instead
 * This action is maintained for backward compatibility only.
 * New code should use the NotificationService directly.
 */
class SendMembershipReminders
{
    use AsAction;

    /**
     * @deprecated Use NotificationService::sendMembershipReminders() instead
     */
    public function handle(bool $dryRun = false, int $inactiveDays = 90): array
    {
        return app(NotificationService::class)->sendMembershipReminders();
    }
}
