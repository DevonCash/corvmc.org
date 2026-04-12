<?php

namespace App\Actions\Notifications;

use App\Services\NotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use NotificationService::sendReservationReminders() instead
 * This action is maintained for backward compatibility only.
 * New code should use the NotificationService directly.
 */
class SendReservationReminders
{
    use AsAction;

    /**
     * @deprecated Use NotificationService::sendReservationReminders() instead
     */
    public function handle(bool $dryRun = false): array
    {
        return app(NotificationService::class)->sendReservationReminders($dryRun);
    }
}
